<?php
/**
 * API für eingehende Rechnungen und Belege
 *
 * Endpoints:
 *   list - Rechnungsliste mit Filtern
 *   get - Einzelne Rechnung abrufen
 *   create - Neue Rechnung erstellen
 *   update - Rechnung aktualisieren
 *   delete - Rechnung löschen (weich)
 *   verify - Rechnung verifizieren
 *   mark_paid - Als bezahlt markieren
 *   cancel - Rechnung stornieren
 *   attach_file - Datei anhängen
 *   remove_file - Datei entfernen
 *   stats - Statistiken
 *   expense_categories - Ausgabenkategorien
 *   create_category - Neue Kategorie
 *   update_category - Kategorie aktualisieren
 *   audit_log - Audit-Trail
 *   compliance_check - GoBD-Compliance-Prüfung
 *   retention_info - Aufbewahrungsinformationen
 *   link_bank_transaction - Mit Bank-Transaktion verknüpfen
 *   search_vendor - Lieferantensuche
 *   duplicate_check - Duplikatsprüfung
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/IncomingInvoiceService.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['user']['users_userid'];

$service = new IncomingInvoiceService($DBLIB);

// Permissions prüfen
if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:VIEW')) {
    finish(false, ['code' => 'PERMISSIONS']);
}

try {
    switch ($action) {
        case 'list':
            $filters = [
                'instances_id' => $instanceId,
                'status' => $_GET['status'] ?? null,
                'vendor' => $_GET['vendor'] ?? null,
                'project_id' => (int)($_GET['project_id'] ?? 0) ?: null,
                'category_id' => (int)($_GET['category_id'] ?? 0) ?: null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'document_type' => $_GET['document_type'] ?? null,
                'limit' => (int)($_GET['limit'] ?? 50),
                'offset' => (int)($_GET['offset'] ?? 0),
                'order_by' => $_GET['order_by'] ?? 'document_date',
                'order_dir' => $_GET['order_dir'] ?? 'DESC',
            ];

            $invoices = $service->list($filters);
            finish(true, null, ['invoices' => $invoices]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            finish(true, null, ['invoice' => $invoice]);
            break;

        case 'create':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:CREATE')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $data = [
                'instances_id' => $instanceId,
                'document_type' => $_POST['document_type'] ?? 'invoice',
                'document_number' => $_POST['document_number'] ?? null,
                'vendor_name' => $_POST['vendor_name'] ?? '',
                'vendor_vat_id' => $_POST['vendor_vat_id'] ?? null,
                'description' => $_POST['description'] ?? null,
                'gross_amount' => (float)($_POST['gross_amount'] ?? 0),
                'net_amount' => !empty($_POST['net_amount']) ? (float)$_POST['net_amount'] : null,
                'vat_amount' => !empty($_POST['vat_amount']) ? (float)$_POST['vat_amount'] : null,
                'vat_rate' => !empty($_POST['vat_rate']) ? (float)$_POST['vat_rate'] : null,
                'currency' => $_POST['currency'] ?? 'EUR',
                'document_date' => $_POST['document_date'] ?? date('Y-m-d'),
                'received_date' => $_POST['received_date'] ?? date('Y-m-d'),
                'due_date' => $_POST['due_date'] ?? null,
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
                'cost_center' => $_POST['cost_center'] ?? null,
                'tax_deductible' => !empty($_POST['tax_deductible']) ? 1 : 0,
                'status' => $_POST['status'] ?? 'draft',
                'notes' => $_POST['notes'] ?? null,
                'is_hospitality' => !empty($_POST['is_hospitality']) ? 1 : 0,
                'hospitality_occasion' => $_POST['hospitality_occasion'] ?? null,
                'hospitality_attendees' => $_POST['hospitality_attendees'] ?? null,
                'hospitality_business_relation' => $_POST['hospitality_business_relation'] ?? null,
                'hospitality_tip_amount' => !empty($_POST['hospitality_tip_amount']) ? (float)$_POST['hospitality_tip_amount'] : null,
            ];

            $id = $service->create($data, $userId);
            finish(true, null, ['id' => $id, 'invoice_id' => $id]);
            break;

        case 'update':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:EDIT')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $data = [];
            $updateFields = ['document_type', 'document_number', 'vendor_name', 'vendor_vat_id', 'description',
                'gross_amount', 'net_amount', 'vat_amount', 'vat_rate', 'currency', 'document_date', 'received_date',
                'due_date', 'category_id', 'project_id', 'cost_center', 'tax_deductible', 'status', 'notes',
                'is_hospitality', 'hospitality_occasion', 'hospitality_attendees', 'hospitality_business_relation', 'hospitality_tip_amount'];

            foreach ($updateFields as $field) {
                if (isset($_POST[$field])) {
                    if (in_array($field, ['gross_amount', 'net_amount', 'vat_amount', 'vat_rate', 'hospitality_tip_amount'])) {
                        $data[$field] = !empty($_POST[$field]) ? (float)$_POST[$field] : null;
                    } elseif (in_array($field, ['category_id', 'project_id'])) {
                        $data[$field] = !empty($_POST[$field]) ? (int)$_POST[$field] : null;
                    } elseif (in_array($field, ['tax_deductible', 'is_hospitality'])) {
                        $data[$field] = !empty($_POST[$field]) ? 1 : 0;
                    } else {
                        $data[$field] = $_POST[$field];
                    }
                }
            }

            $success = $service->update($id, $data, $userId);
            finish($success, $success ? null : ['code' => 'UPDATE_FAILED']);
            break;

        case 'delete':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:DELETE')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $reason = $_POST['reason'] ?? 'Keine Angabe';
            $success = $service->delete($id, $userId, $reason);
            finish($success, $success ? null : ['code' => 'DELETE_FAILED']);
            break;

        case 'verify':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:VERIFY')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $success = $service->verify($id, $userId);
            finish($success, $success ? null : ['code' => 'VERIFY_FAILED']);
            break;

        case 'mark_paid':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:EDIT')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $paymentData = [
                'paid_date' => $_POST['paid_date'] ?? date('Y-m-d'),
                'payment_method' => $_POST['payment_method'] ?? null,
                'payment_reference' => $_POST['payment_reference'] ?? null,
                'bank_transaction_id' => !empty($_POST['bank_transaction_id']) ? (int)$_POST['bank_transaction_id'] : null,
            ];

            $success = $service->markPaid($id, $userId, $paymentData);
            finish($success, $success ? null : ['code' => 'MARK_PAID_FAILED']);
            break;

        case 'cancel':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:EDIT')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $reason = $_POST['reason'] ?? 'Keine Angabe';
            $success = $service->cancel($id, $userId, $reason);
            finish($success, $success ? null : ['code' => 'CANCEL_FAILED']);
            break;

        case 'attach_file':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:EDIT')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $invoiceId = (int)($_POST['invoice_id'] ?? 0);
            $s3fileId = (int)($_POST['s3file_id'] ?? 0);
            $fileType = $_POST['file_type'] ?? 'original';

            if ($invoiceId <= 0 || $s3fileId <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($invoiceId);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $fileId = $service->attachFile($invoiceId, $s3fileId, $fileType, $userId);
            finish(true, null, ['file_id' => $fileId]);
            break;

        case 'remove_file':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:EDIT')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $fileId = (int)($_POST['file_id'] ?? 0);
            if ($fileId <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $success = $service->removeFile($fileId, $userId);
            finish($success, $success ? null : ['code' => 'REMOVE_FAILED']);
            break;

        case 'stats':
            $stats = $service->getStats(['instances_id' => $instanceId]);
            finish(true, null, ['stats' => $stats]);
            break;

        case 'expense_categories':
            $categories = $service->getExpenseCategories($instanceId);
            finish(true, null, ['categories' => $categories]);
            break;

        case 'create_category':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:EDIT')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $data = [
                'instances_id' => $instanceId,
                'name' => $_POST['name'] ?? '',
                'skr03_account' => $_POST['skr03_account'] ?? null,
                'skr04_account' => $_POST['skr04_account'] ?? null,
                'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
                'sort_order' => !empty($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0,
            ];

            if (empty($data['name'])) {
                finish(false, ['code' => 'INVALID']);
            }

            $id = $service->createExpenseCategory($data);
            finish(true, null, ['id' => $id]);
            break;

        case 'update_category':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:EDIT')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $data = [];
            if (isset($_POST['name'])) $data['name'] = $_POST['name'];
            if (isset($_POST['skr03_account'])) $data['skr03_account'] = $_POST['skr03_account'];
            if (isset($_POST['skr04_account'])) $data['skr04_account'] = $_POST['skr04_account'];
            if (isset($_POST['sort_order'])) $data['sort_order'] = (int)$_POST['sort_order'];

            $success = $service->updateExpenseCategory($id, $data);
            finish($success, $success ? null : ['code' => 'UPDATE_FAILED']);
            break;

        case 'audit_log':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $log = $service->getAuditLog($id);
            finish(true, null, ['audit_log' => $log]);
            break;

        case 'compliance_check':
            $issues = $service->checkGobdCompliance($instanceId);
            finish(true, null, ['issues' => $issues, 'compliant' => empty($issues)]);
            break;

        case 'retention_info':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $info = $service->getRetentionInfo($id);
            finish(true, null, ['retention_info' => $info]);
            break;

        case 'link_bank_transaction':
            if (!$AUTH->instancePermissionCheck('ACCOUNTING:INCOMING_INVOICES:EDIT')) {
                finish(false, ['code' => 'PERMISSIONS']);
            }

            $id = (int)($_POST['id'] ?? 0);
            $transactionId = (int)($_POST['transaction_id'] ?? 0);

            if ($id <= 0 || $transactionId <= 0) {
                finish(false, ['code' => 'INVALID']);
            }

            $invoice = $service->get($id);
            if (!$invoice || $invoice['instances_id'] !== $instanceId) {
                finish(false, ['code' => 'NOT_FOUND']);
            }

            $success = $service->linkToBankTransaction($id, $transactionId, $userId);
            finish($success, $success ? null : ['code' => 'LINK_FAILED']);
            break;

        case 'search_vendor':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                finish(true, null, ['results' => []]);
            }

            $results = $service->searchByVendor($query, $instanceId);
            finish(true, null, ['results' => $results]);
            break;

        case 'duplicate_check':
            $vendor = $_GET['vendor'] ?? '';
            $documentNumber = $_GET['document_number'] ?? null;
            $amount = $_GET['amount'] ?? '0';

            if (empty($vendor) || empty($amount)) {
                finish(false, ['code' => 'INVALID']);
            }

            $duplicates = $service->getDuplicateCheck($vendor, $documentNumber, (string)$amount, $instanceId);
            finish(true, null, ['duplicates' => $duplicates, 'count' => count($duplicates)]);
            break;

        default:
            finish(false, ['code' => 'INVALID_ACTION']);
    }
} catch (Exception $e) {
    finish(false, ['code' => 'ERROR', 'message' => $e->getMessage()]);
}
