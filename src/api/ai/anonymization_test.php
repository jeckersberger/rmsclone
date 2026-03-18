<?php
/**
 * Test Anonymization API
 *
 * POST /api/ai/anonymization_test
 *
 * Test how text would be anonymized without actually processing a request.
 * Shows what patterns would be matched and replaced.
 *
 * Request:
 * {
 *   "text": "Sample text to anonymize",
 *   "mode": "strict" (optional, defaults to instance config)
 * }
 *
 * Response:
 * {
 *   "success": true,
 *   "original": "Sample text...",
 *   "anonymized": "Sample [PERSON_0]...",
 *   "replacements": {
 *     "[PERSON_0]": "John Doe",
 *     "[EMAIL_0]": "john@example.com"
 *   },
 *   "stats": {
 *     "PERSON": 1,
 *     "EMAIL": 1
 *   }
 * }
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:VIEW')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$instanceId = (int)$_SESSION['instance_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['text'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing text parameter']));
}

$text = $data['text'];
$mode = $data['mode'] ?? null;

// If mode not specified, get from config
if (!$mode) {
    $db->where('instances_id', $instanceId);
    $config = $db->getOne('ai_anonymization_config');
    $mode = $config['mode'] ?? 'strict';
}

if (!in_array($mode, ['strict', 'standard', 'minimal', 'off'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid mode']));
}

try {
    $anonymizer = new AnonymizationService($db);

    $anonymized = $anonymizer->anonymize($text, $mode, $instanceId);
    $stats = $anonymizer->getReplacementTypes();
    $count = $anonymizer->getReplacementCount();

    // Build replacement details (just the mapping structure, not the actual strings in normal response)
    $replacementsSummary = [];
    foreach ($stats as $type => $typeCount) {
        $replacementsSummary[$type] = $typeCount;
    }

    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'original_length' => strlen($text),
        'anonymized_length' => strlen($anonymized),
        'replacements_count' => $count,
        'replacement_types' => $replacementsSummary,
        'anonymized_text' => $anonymized,
        // Don't expose actual replacement mapping to avoid leaking PII in API responses
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Anonymization failed: ' . $e->getMessage()]);
}
