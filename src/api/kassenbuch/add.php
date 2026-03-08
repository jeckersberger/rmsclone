<?php
/**
 * Kassenbuch: Neuen Eintrag hinzufuegen
 * POST: entry_date, description, amount, type (einnahme|ausgabe), category, receipt_number, payment_method, notes
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_STATS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];

require_once __DIR__ . '/../../services/KassenbuchService.php';
$service = new KassenbuchService($DBLIB);

$result = $service->addEntry($instanceId, [
    'entry_date'     => $_POST['entry_date'] ?? null,
    'description'    => $_POST['description'] ?? '',
    'amount'         => $_POST['amount'] ?? 0,
    'type'           => $_POST['type'] ?? 'ausgabe',
    'category'       => $_POST['category'] ?? null,
    'receipt_number' => $_POST['receipt_number'] ?? null,
    'payment_method' => $_POST['payment_method'] ?? null,
    'notes'          => $_POST['notes'] ?? null,
]);

if ($result['success']) {
    finish(true, null, $result);
} else {
    finish(false, ["message" => $result['message']]);
}
