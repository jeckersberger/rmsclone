<?php
/**
 * AI-powered invoice/receipt scanning API
 * Extracts invoice data from photos and PDFs using Claude AI
 *
 * POST Parameters:
 *   action = scan_invoice
 *   file = uploaded image or PDF file
 *
 * Returns extracted data: vendor_name, amount, date, document_number, vat_rate, category
 */
require_once __DIR__ . '/../apiHeadSecure.php';

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 0);

if (!$instanceId) {
    finish(false, ['code' => 'INVALID_INSTANCE']);
}

// Check permission
if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:CREATE')) {
    finish(false, ['code' => 'PERMISSIONS']);
}

require_once __DIR__ . '/../../services/ClaudeService.php';

try {
    switch ($action) {
        case 'scan_invoice':
            handleInvoiceScan($instanceId);
            break;

        default:
            finish(false, ['code' => 'INVALID_ACTION']);
    }
} catch (Exception $e) {
    finish(false, ['code' => 'ERROR', 'message' => $e->getMessage()]);
}

function handleInvoiceScan(int $instanceId): void
{
    global $DBLIB;

    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        finish(false, ['code' => 'NO_FILE']);
    }

    $file = $_FILES['file'];
    $tmpPath = $file['tmp_name'];
    $fileName = basename($file['name']);
    $mimeType = $file['type'];

    // Validate file size
    if (filesize($tmpPath) > 10 * 1024 * 1024) { // 10MB limit
        finish(false, ['code' => 'FILE_TOO_LARGE']);
    }

    // Determine if it's an image or PDF
    $isImage = in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp']);
    $isPdf = $mimeType === 'application/pdf';

    if (!$isImage && !$isPdf) {
        finish(false, ['code' => 'INVALID_FILE_TYPE', 'message' => 'Only JPEG, PNG, WebP images or PDF allowed']);
    }

    // Read and encode file
    $fileData = file_get_contents($tmpPath);
    $base64File = base64_encode($fileData);

    // Call Claude AI to extract invoice data
    $extractedData = callClaudeAI($base64File, $mimeType);

    if (!$extractedData) {
        finish(true, null, [
            'success' => true,
            'extracted' => null,
            'message' => 'No invoice data could be extracted from the file'
        ]);
    }

    finish(true, null, [
        'success' => true,
        'extracted' => $extractedData
    ]);
}

/**
 * Call Claude AI to extract invoice data from image or PDF
 *
 * @param string $base64File Base64-encoded file
 * @param string $mimeType MIME type of the file
 * @return array|null Extracted data or null
 */
function callClaudeAI(string $base64File, string $mimeType): ?array
{
    global $DBLIB;

    // Get Claude API configuration
    $DBLIB->where('instances_id', 0); // System config
    $config = $DBLIB->getOne('config', ['config_value']);
    $apiKey = getenv('CLAUDE_API_KEY');

    if (!$apiKey) {
        error_log('Claude API key not configured');
        return null;
    }

    // Prepare Claude API request based on file type
    $isImage = strpos($mimeType, 'image/') === 0;
    $isPdf = $mimeType === 'application/pdf';

    $prompt = <<<'PROMPT'
Analyze this invoice or receipt and extract the following information in German context:
- vendor_name: The name of the vendor/seller
- amount: The total amount (gross), as a decimal number
- date: The invoice date in YYYY-MM-DD format
- document_number: The invoice/receipt number if visible
- vat_rate: The VAT rate as a percentage (e.g., 19 for 19%)
- category: A suggested expense category (e.g., "Office Supplies", "Food & Beverage", "Software", "Travel")

Return the response as a JSON object with these exact keys. Only include fields that are clearly visible.
If the value is not found or unclear, omit the key from the JSON response.

Example response format:
{"vendor_name": "ACME Company", "amount": 123.45, "date": "2025-03-16", "document_number": "INV-2025-001", "vat_rate": 19, "category": "Office Supplies"}
PROMPT;

    // Determine media type for Claude API
    $mediaType = $mimeType;
    if ($mimeType === 'application/pdf') {
        $mediaType = 'application/pdf';
    }

    $postData = json_encode([
        'model' => 'claude-3-5-sonnet-20241022',
        'max_tokens' => 1024,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $isImage ? str_replace('image/', 'image/', $mimeType) : 'image/png',
                            'data' => $base64File
                        ]
                    ],
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ]
                ]
            ]
        ]
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.anthropic.com/v1/messages',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('Claude API error: ' . $httpCode . ' - ' . substr($response, 0, 500));
        return null;
    }

    $responseData = json_decode($response, true);
    if (!$responseData || !isset($responseData['content'][0]['text'])) {
        error_log('Invalid Claude API response');
        return null;
    }

    // Extract JSON from response
    $text = $responseData['content'][0]['text'];
    $jsonMatch = preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/', $text, $matches);

    if (!$jsonMatch) {
        error_log('No JSON found in Claude response: ' . $text);
        return null;
    }

    $extracted = json_decode($matches[0], true);
    if (!is_array($extracted)) {
        error_log('Invalid JSON from Claude: ' . $matches[0]);
        return null;
    }

    // Validate and clean extracted data
    $cleaned = [];

    if (!empty($extracted['vendor_name'])) {
        $cleaned['vendor_name'] = trim($extracted['vendor_name']);
    }

    if (!empty($extracted['amount'])) {
        $amount = floatval(str_replace(',', '.', $extracted['amount']));
        if ($amount > 0) {
            $cleaned['amount'] = $amount;
        }
    }

    if (!empty($extracted['date'])) {
        // Validate date format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $extracted['date'])) {
            $cleaned['date'] = $extracted['date'];
        }
    }

    if (!empty($extracted['document_number'])) {
        $cleaned['document_number'] = trim($extracted['document_number']);
    }

    if (!empty($extracted['vat_rate'])) {
        $vat = floatval($extracted['vat_rate']);
        if ($vat >= 0 && $vat <= 100) {
            $cleaned['vat_rate'] = $vat;
        }
    }

    if (!empty($extracted['category'])) {
        $cleaned['category'] = trim($extracted['category']);
    }

    return !empty($cleaned) ? $cleaned : null;
}
