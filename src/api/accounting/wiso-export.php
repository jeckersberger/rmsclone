<?php
/**
 * WISO Export API Endpoint
 *
 * Generates WISO-compatible CSV exports for EÜR (Einnahmenüberschussrechnung).
 * Supports both CSV download and JSON response.
 *
 * GET Parameters:
 * - year: The year to export (required if no date range specified)
 * - kontenrahmen: 'SKR03' or 'SKR04' (default: SKR03)
 * - format: 'csv' or 'json' (default: csv)
 */

require_once __DIR__ . '/../apiHeadSecure.php';

// Permission check
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

// Get instance ID from auth data
$instanceId = $AUTH->data['instance']['instances_id'];

// Get parameters
$year = (int)($_REQUEST['year'] ?? date('Y'));
$kontenrahmen = $_REQUEST['kontenrahmen'] ?? 'SKR03';
$format = $_REQUEST['format'] ?? 'csv';

// Validate kontenrahmen
if (!in_array(strtoupper($kontenrahmen), ['SKR03', 'SKR04'])) {
    $kontenrahmen = 'SKR03';
}

// Load service
require_once __DIR__ . '/../../services/WisoExportService.php';
$service = new WisoExportService($DBLIB);

// Generate exports
$exports = $service->exportAll($instanceId, $year, $kontenrahmen);

if ($format === 'json') {
    // Return JSON response
    $response = [
        'success' => true,
        'year' => $year,
        'kontenrahmen' => $kontenrahmen,
        'summary' => $exports['summary'],
        'invoices_csv' => $exports['invoices_csv'],
        'payments_csv' => $exports['payments_csv'],
    ];
    finish(true, null, $response);
} else {
    // Return CSV download
    $bom = "\xEF\xBB\xBF"; // UTF-8 BOM
    $filename = "WISO_Export_{$kontenrahmen}_{$year}.csv";

    // Combine invoices and payments with section headers
    $csvContent = $bom;
    $csvContent .= "# WISO Export {$year} ({$kontenrahmen})\r\n";
    $csvContent .= "# Einnahmen (Invoices)\r\n";
    $csvContent .= $exports['invoices_csv'];

    if (!empty($exports['payments_csv'])) {
        $csvContent .= "\r\n# Zahlungseingaenge (Payments)\r\n";
        $csvContent .= $exports['payments_csv'];
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csvContent;
    exit;
}
?>
