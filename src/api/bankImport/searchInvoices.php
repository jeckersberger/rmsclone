<?php
/**
 * Offene Rechnungen suchen (fuer manuelles Matching).
 *
 * GET: q (Suchbegriff), amount (optional, Betrag zum Sortieren)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$query = trim($_REQUEST['q'] ?? '');
$amount = !empty($_REQUEST['amount']) ? (float)str_replace(',', '.', $_REQUEST['amount']) : null;

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

$invoices = $service->searchInvoicesForMatch($instanceId, $query, $amount);
finish(true, null, ["invoices" => $invoices]);
