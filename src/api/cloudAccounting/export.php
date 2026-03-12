<?php
/**
 * Cloud-Buchhaltung Export (lexoffice, sevDesk)
 *
 * GET/POST Parameter:
 *   provider = lexoffice | sevdesk
 *   type     = invoices | contacts
 *   from     = Startdatum (YYYY-MM-DD)
 *   to       = Enddatum (YYYY-MM-DD)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/CloudAccountingExportService.php';

if (!$AUTH->instancePermissionCheck("DATEV:EXPORT") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$provider = $_REQUEST['provider'] ?? '';
$type = $_REQUEST['type'] ?? 'invoices';
$from = $_REQUEST['from'] ?? date('Y-01-01');
$to = $_REQUEST['to'] ?? date('Y-m-d');

if (!in_array($provider, ['lexoffice', 'sevdesk'])) {
    finish(false, ["error" => "Ungueltiger Provider. Erlaubt: lexoffice, sevdesk"]);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    finish(false, ["error" => "Ungueltiges Datumsformat. Erwartet: YYYY-MM-DD"]);
}

$svc = new CloudAccountingExportService($DBLIB);

if ($provider === 'lexoffice') {
    if ($type === 'contacts') {
        $result = $svc->exportLexofficeContacts($instanceId);
    } else {
        $result = $svc->exportLexoffice($instanceId, $from, $to);
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
    echo $result['json'];
    exit;
}

if ($provider === 'sevdesk') {
    if ($type === 'contacts') {
        $result = $svc->exportSevdeskContacts($instanceId);
    } else {
        $result = $svc->exportSevdesk($instanceId, $from, $to);
    }

    $bom = "\xEF\xBB\xBF";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
    echo $bom . $result['csv'];
    exit;
}
