<?php
/**
 * Gescannte Eingangsrechnung bestaetigen und als EUeR-Buchung verbuchen
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/EuerService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];
$receiptId = (int)($_POST['receipt_id'] ?? 0);

if (!$receiptId) finish(false, ["code" => "INVALID"]);

// Get receipt
$DBLIB->where('id', $receiptId);
$DBLIB->where('instances_id', $instanceId);
$receipt = $DBLIB->getOne('expense_receipts');
if (!$receipt) finish(false, ["code" => "NOT_FOUND"]);

// Allow updating extracted fields
$updateData = ['status' => 'confirmed'];
$fields = ['vendor_name','invoice_number','invoice_date','net_amount','vat_amount','gross_amount','vat_rate','euer_categories_id','notes'];
foreach ($fields as $f) {
    if (isset($_POST[$f])) $updateData[$f] = $_POST[$f];
}

$DBLIB->where('id', $receiptId);
$DBLIB->update('expense_receipts', $updateData);

// Auto-book to EUeR if category is set and action=book
if (!empty($_POST['book']) && !empty($updateData['euer_categories_id'])) {
    $euer = new EuerService($DBLIB);
    $bookingId = $euer->addBooking($instanceId, [
        'category_id' => (int)$updateData['euer_categories_id'],
        'date' => $updateData['invoice_date'] ?? $receipt['invoice_date'] ?? date('Y-m-d'),
        'description' => ($updateData['vendor_name'] ?? $receipt['vendor_name'] ?? 'Eingangsrechnung') .
                         ($receipt['invoice_number'] ? " #{$receipt['invoice_number']}" : ''),
        'amount' => (float)($updateData['gross_amount'] ?? $receipt['gross_amount'] ?? 0),
        'vat_amount' => (float)($updateData['vat_amount'] ?? $receipt['vat_amount'] ?? 0),
    ], $userId);

    $DBLIB->where('id', $receiptId);
    $DBLIB->update('expense_receipts', ['status' => 'booked', 'euer_bookings_id' => $bookingId]);

    finish(true, null, ['receipt_id' => $receiptId, 'booking_id' => $bookingId, 'status' => 'booked']);
}

finish(true, null, ['receipt_id' => $receiptId, 'status' => 'confirmed']);
