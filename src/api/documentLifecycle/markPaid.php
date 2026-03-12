<?php
/**
 * Rechnung als (teilweise) bezahlt markieren.
 *
 * POST: doc_id, amount, reference (optional), partial (optional, 0|1)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DOCUMENTS:EDIT") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$docId = (int)($_POST['doc_id'] ?? 0);
$amount = (float)str_replace(',', '.', $_POST['amount'] ?? '0');
$reference = trim($_POST['reference'] ?? '');
$isPartial = (int)($_POST['partial'] ?? 0);
$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

if ($docId <= 0 || $amount <= 0) {
    finish(false, ["code" => null, "message" => "Ungueltige Parameter."]);
}

// Load the document
$DBLIB->where('id', $docId);
$DBLIB->where('instances_id', $instanceId);
$doc = $DBLIB->getOne('document_lifecycle');

if (!$doc || $doc['doc_type'] !== 'invoice') {
    finish(false, ["code" => null, "message" => "Rechnung nicht gefunden."]);
}

$previousPaid = (float)($doc['paid_amount'] ?? 0);
$totalPaid = round($previousPaid + $amount, 2);
$grossAmount = (float)$doc['gross_amount'];
$remaining = round($grossAmount - $totalPaid, 2);

// Update document
$updateData = [
    'paid_amount' => $totalPaid,
];

$lifecycle = new DocumentLifecycleService($DBLIB);

if ($remaining <= 0.01 || !$isPartial) {
    // Fully paid
    $updateData['status'] = 'paid';
    $updateData['paid_date'] = date('Y-m-d');
    $DBLIB->where('id', $docId);
    $DBLIB->update('document_lifecycle', $updateData);
    $lifecycle->changeStatus($docId, 'paid', $userId,
        "Zahlung erhalten: " . number_format($amount, 2, ',', '.') . " EUR"
        . ($reference ? " (Ref: {$reference})" : '')
        . ($previousPaid > 0 ? " - Gesamt bezahlt: " . number_format($totalPaid, 2, ',', '.') . " EUR" : '')
    );
} else {
    // Partial payment - keep status as sent/overdue/reminded
    $DBLIB->where('id', $docId);
    $DBLIB->update('document_lifecycle', $updateData);

    // Log the partial payment in status history
    $DBLIB->insert('document_status_history', [
        'document_lifecycle_id' => $docId,
        'old_status' => $doc['status'],
        'new_status' => $doc['status'],
        'comment' => "Teilzahlung: " . number_format($amount, 2, ',', '.') . " EUR"
            . ($reference ? " (Ref: {$reference})" : '')
            . " - Offen: " . number_format($remaining, 2, ',', '.') . " EUR",
        'changed_by' => $userId,
    ]);
}

finish(true, null, [
    'total_paid' => $totalPaid,
    'remaining' => max(0, $remaining),
    'status' => ($remaining <= 0.01 || !$isPartial) ? 'paid' : $doc['status'],
    'fully_paid' => ($remaining <= 0.01),
]);
