<?php
/**
 * Eingangsrechnungen auflisten (fuer EUeR-Ansicht)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$status = trim($_POST['status'] ?? '');
$year = (int)($_POST['year'] ?? date('Y'));

$DBLIB->where('er.instances_id', $instanceId);
if ($status) $DBLIB->where('er.status', $status);
if ($year) {
    $DBLIB->where('er.created_at', "{$year}-01-01", '>=');
    $DBLIB->where('er.created_at', "{$year}-12-31 23:59:59", '<=');
}
$DBLIB->join('euer_categories ec', 'er.euer_categories_id=ec.id', 'LEFT');
$DBLIB->orderBy('er.created_at', 'DESC');
$receipts = $DBLIB->get('expense_receipts er', null, [
    'er.*', 'ec.name as category_name'
]) ?: [];

finish(true, null, ['receipts' => $receipts]);
