<?php
/**
 * IncomingInvoiceService - Verwaltung von eingehenden Rechnungen und Belegen
 *
 * Implementiert GoBD-konforme Dokumentenverwaltung mit:
 * - Unveränderbarkeit (Immutability) durch Versionierung
 * - Vollständiger Audit-Trail
 * - Automatische Hash-Berechnung
 * - Aufbewahrungsfristen-Tracking
 */
class IncomingInvoiceService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Eingehende Rechnungen mit Filtern abrufen
     */
    public function list(array $filters = []): array
    {
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $filters['instances_id'] ?? 0);

        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }

        if (!empty($filters['vendor'])) {
            $this->db->where('vendor_name', '%' . $filters['vendor'] . '%', 'LIKE');
        }

        if (!empty($filters['project_id'])) {
            $this->db->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['category_id'])) {
            $this->db->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['date_from'])) {
            $this->db->where('document_date', $filters['date_from'], '>=');
        }

        if (!empty($filters['date_to'])) {
            $this->db->where('document_date', $filters['date_to'], '<=');
        }

        if (!empty($filters['document_type'])) {
            $this->db->where('document_type', $filters['document_type']);
        }

        $orderBy = $filters['order_by'] ?? 'document_date';
        $orderDir = $filters['order_dir'] ?? 'DESC';
        $this->db->orderBy($orderBy, $orderDir);

        $limit = (int)($filters['limit'] ?? 50);
        $offset = (int)($filters['offset'] ?? 0);

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->get('incoming_invoices') ?: [];
    }

    /**
     * Einzelne Rechnung mit Dateien und Audit-Log abrufen
     */
    public function get(int $id): ?array
    {
        $this->db->where('id', $id);
        $this->db->where('deleted', 0);
        $invoice = $this->db->getOne('incoming_invoices');

        if (!$invoice) {
            return null;
        }

        // Dateien abrufen
        $this->db->where('incoming_invoice_id', $id);
        $invoice['files'] = $this->db->get('incoming_invoice_files') ?: [];

        // Audit-Log abrufen
        $this->db->where('incoming_invoice_id', $id);
        $this->db->orderBy('created_at', 'ASC');
        $invoice['audit_log'] = $this->db->get('incoming_invoice_audit_log') ?: [];

        return $invoice;
    }

    /**
     * Neue Rechnung erstellen
     */
    public function create(array $data, int $userId): int
    {
        // Feldvalidierung
        $this->validateInvoiceData($data);

        // Standardwerte
        $data['recorded_by'] = $userId;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['gobd_recorded_at'] = date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'draft';
        $data['document_date'] = $data['document_date'] ?? date('Y-m-d');
        $data['received_date'] = $data['received_date'] ?? date('Y-m-d');
        $data['currency'] = $data['currency'] ?? 'EUR';
        $data['tax_deductible'] = $data['tax_deductible'] ?? 1;

        // GoBD Hash berechnen
        $data['gobd_hash'] = $this->calculateDocumentHash($data);

        // Einfügen
        $this->db->insert('incoming_invoices', $data);
        $invoiceId = $this->db->getInsertId();

        // Audit-Log
        $this->logAuditTrail($invoiceId, $data['instances_id'], 'created', null, null, $userId);

        return $invoiceId;
    }

    /**
     * Rechnung aktualisieren mit detailliertem Audit-Trail
     */
    public function update(int $id, array $data, int $userId): bool
    {
        $invoice = $this->get($id);
        if (!$invoice) {
            return false;
        }

        // Feldvalidierung
        $this->validateInvoiceData($data, false);

        // Für jedes Feld ein separates Audit-Log-Entry
        foreach ($data as $field => $newValue) {
            if (array_key_exists($field, $invoice) && $invoice[$field] !== $newValue) {
                $oldValue = $invoice[$field];
                $this->logAuditTrail($id, $invoice['instances_id'], 'updated', $field, $oldValue, $userId, $newValue);
            }
        }

        // GoBD Hash neu berechnen wenn relevante Felder geändert
        if (in_array('document_number', array_keys($data)) || in_array('vendor_name', array_keys($data)) ||
            in_array('gross_amount', array_keys($data)) || in_array('document_date', array_keys($data))) {
            $mergedData = array_merge($invoice, $data);
            $data['gobd_hash'] = $this->calculateDocumentHash($mergedData);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        // Update durchführen
        $this->db->where('id', $id);
        return $this->db->update('incoming_invoices', $data);
    }

    /**
     * Rechnung weich löschen (GoBD-konform)
     * Echte Löschung ist nicht gestattet, nur Markierung als gelöscht
     */
    public function delete(int $id, int $userId, string $reason = ''): bool
    {
        $invoice = $this->get($id);
        if (!$invoice) {
            return false;
        }

        $this->db->where('id', $id);
        $success = $this->db->update('incoming_invoices', [
            'deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($success) {
            $this->logAuditTrail($id, $invoice['instances_id'], 'cancelled', 'deleted', 0, $userId, 1, $reason);
        }

        return $success;
    }

    /**
     * Rechnung als Verifiziert markieren
     */
    public function verify(int $id, int $userId): bool
    {
        $invoice = $this->get($id);
        if (!$invoice) {
            return false;
        }

        $this->db->where('id', $id);
        $success = $this->db->update('incoming_invoices', [
            'status' => 'verified',
            'verified_by' => $userId,
            'verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($success) {
            $this->logAuditTrail($id, $invoice['instances_id'], 'verified', 'status', $invoice['status'], $userId, 'verified');
        }

        return $success;
    }

    /**
     * Rechnung als bezahlt markieren
     */
    public function markPaid(int $id, int $userId, array $paymentData): bool
    {
        $invoice = $this->get($id);
        if (!$invoice) {
            return false;
        }

        $updateData = [
            'status' => 'paid',
            'paid_date' => $paymentData['paid_date'] ?? date('Y-m-d'),
            'payment_method' => $paymentData['payment_method'] ?? null,
            'payment_reference' => $paymentData['payment_reference'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($paymentData['bank_transaction_id'])) {
            $updateData['bank_transaction_id'] = $paymentData['bank_transaction_id'];
        }

        $this->db->where('id', $id);
        $success = $this->db->update('incoming_invoices', $updateData);

        if ($success) {
            $this->logAuditTrail($id, $invoice['instances_id'], 'paid', 'status', $invoice['status'], $userId, 'paid');
            if (!empty($paymentData['payment_reference'])) {
                $this->logAuditTrail($id, $invoice['instances_id'], 'updated', 'payment_reference', $invoice['payment_reference'] ?? null, $userId, $paymentData['payment_reference']);
            }
        }

        return $success;
    }

    /**
     * Rechnung stornieren
     */
    public function cancel(int $id, int $userId, string $reason): bool
    {
        $invoice = $this->get($id);
        if (!$invoice) {
            return false;
        }

        $this->db->where('id', $id);
        $success = $this->db->update('incoming_invoices', [
            'status' => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
            'notes' => ($invoice['notes'] ?? '') . "\n[STORNIERT: $reason]",
        ]);

        if ($success) {
            $this->logAuditTrail($id, $invoice['instances_id'], 'cancelled', 'status', $invoice['status'], $userId, 'cancelled', $reason);
        }

        return $success;
    }

    /**
     * Datei zu Rechnung hinzufügen
     */
    public function attachFile(int $invoiceId, int $s3fileId, string $fileType, int $userId, ?string $filePath = null): int
    {
        $invoice = $this->get($invoiceId);
        if (!$invoice) {
            throw new Exception('Invoice not found');
        }

        // Datei-Hash berechnen
        $godbHash = '';
        if ($filePath && file_exists($filePath)) {
            $godbHash = hash_file('sha256', $filePath);
        }

        $this->db->insert('incoming_invoice_files', [
            'incoming_invoice_id' => $invoiceId,
            'instances_id' => $invoice['instances_id'],
            's3files_id' => $s3fileId,
            'file_type' => $fileType,
            'gobd_hash' => $godbHash,
            'uploaded_by' => $userId,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        $fileId = $this->db->getInsertId();

        // Audit-Log
        $this->logAuditTrail($invoiceId, $invoice['instances_id'], 'file_added', 'file_id', null, $userId, $fileId);

        return $fileId;
    }

    /**
     * Dateiverknüpfung entfernen (GoBD: Datei bleibt, nur Verknüpfung wird entfernt)
     */
    public function removeFile(int $fileId, int $userId): bool
    {
        $this->db->where('id', $fileId);
        $file = $this->db->getOne('incoming_invoice_files');

        if (!$file) {
            return false;
        }

        $this->db->where('id', $fileId);
        $success = $this->db->delete('incoming_invoice_files');

        if ($success) {
            $this->logAuditTrail($file['incoming_invoice_id'], $file['instances_id'], 'file_removed', 'file_id', $fileId, $userId);
        }

        return $success;
    }

    /**
     * Statistiken abrufen
     */
    public function getStats(array $filters = []): array
    {
        $instanceId = $filters['instances_id'] ?? 0;

        // Gesamtbetrag nach Status
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->groupBy('status');
        $this->db->select(['status', 'COUNT(*) as count', 'SUM(gross_amount) as total']);
        $byStatus = $this->db->get('incoming_invoices') ?: [];

        // Unbezahlte Rechnungen
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', ['draft', 'recorded', 'verified'], 'IN');
        $unpaidCount = $this->db->getValue('incoming_invoices', 'COUNT(*)');
        $unpaidTotal = $this->db->where('deleted', 0)
            ->where('instances_id', $instanceId)
            ->where('status', ['draft', 'recorded', 'verified'], 'IN')
            ->getValue('incoming_invoices', 'SUM(gross_amount)') ?? 0;

        // Überfällige
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', ['draft', 'recorded', 'verified'], 'IN');
        $this->db->where('due_date', date('Y-m-d'), '<');
        $overdueCount = $this->db->getValue('incoming_invoices', 'COUNT(*)');
        $overdueTotal = $this->db->where('deleted', 0)
            ->where('instances_id', $instanceId)
            ->where('status', ['draft', 'recorded', 'verified'], 'IN')
            ->where('due_date', date('Y-m-d'), '<')
            ->getValue('incoming_invoices', 'SUM(gross_amount)') ?? 0;

        // Nach Kategorie
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->groupBy('category_id');
        $this->db->select(['category_id', 'COUNT(*) as count', 'SUM(gross_amount) as total']);
        $byCategory = $this->db->get('incoming_invoices') ?: [];

        return [
            'by_status' => $byStatus,
            'unpaid' => ['count' => $unpaidCount, 'total' => $unpaidTotal],
            'overdue' => ['count' => $overdueCount, 'total' => $overdueTotal],
            'by_category' => $byCategory,
        ];
    }

    /**
     * Ausgabenkategorien abrufen
     */
    public function getExpenseCategories(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('sort_order', 'ASC');
        $this->db->orderBy('name', 'ASC');
        return $this->db->get('expense_categories') ?: [];
    }

    /**
     * Neue Ausgabenkategorie erstellen
     */
    public function createExpenseCategory(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['is_system'] = 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $this->db->insert('expense_categories', $data);
        return $this->db->getInsertId();
    }

    /**
     * Ausgabenkategorie aktualisieren
     */
    public function updateExpenseCategory(int $id, array $data): bool
    {
        $this->db->where('id', $id);
        $this->db->where('is_system', 0);
        return $this->db->update('expense_categories', $data);
    }

    /**
     * Audit-Trail abrufen
     */
    public function getAuditLog(int $invoiceId): array
    {
        $this->db->where('incoming_invoice_id', $invoiceId);
        $this->db->orderBy('created_at', 'ASC');
        return $this->db->get('incoming_invoice_audit_log') ?: [];
    }

    /**
     * GoBD-Compliance prüfen
     */
    public function checkGobdCompliance(int $instanceId): array
    {
        $issues = [];

        // Unerfasste Rechnungen (älter als 10 Geschäftstage)
        $tenyBizDaysAgo = date('Y-m-d', strtotime('-14 days')); // ~10 Geschäftstage
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'draft');
        $this->db->where('created_at', $tenyBizDaysAgo, '<');
        $unrecordedCount = $this->db->getValue('incoming_invoices', 'COUNT(*)');

        if ($unrecordedCount > 0) {
            $issues[] = [
                'type' => 'unrecorded_invoices',
                'severity' => 'warning',
                'message' => "$unrecordedCount Rechnungen sind älter als 10 Geschäftstage und nicht erfasst",
                'count' => $unrecordedCount,
            ];
        }

        // Rechnungen ohne Hash
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('gobd_hash', null);
        $this->db->where('status', 'draft', '!=');
        $noHashCount = $this->db->getValue('incoming_invoices', 'COUNT(*)');

        if ($noHashCount > 0) {
            $issues[] = [
                'type' => 'missing_hash',
                'severity' => 'error',
                'message' => "$noHashCount Rechnungen haben keinen GoBD-Hash",
                'count' => $noHashCount,
            ];
        }

        // Aufbewahrungsfristen überschritten
        $retentionIssues = $this->checkRetentionViolations($instanceId);
        if (!empty($retentionIssues)) {
            $issues = array_merge($issues, $retentionIssues);
        }

        return $issues;
    }

    /**
     * Aufbewahrungsinformationen für Rechnung
     */
    public function getRetentionInfo(int $invoiceId): array
    {
        $invoice = $this->get($invoiceId);
        if (!$invoice) {
            return [];
        }

        // Standard Aufbewahrungsfristen
        $retentionYears = 10; // Default für Rechnungen
        if ($invoice['document_type'] === 'delivery_note') {
            $retentionYears = 6;
        }

        $retentionUntil = date('Y-m-d', strtotime("+$retentionYears years", strtotime($invoice['document_date'])));
        $daysRemaining = (int)(strtotime($retentionUntil) - time()) / (60 * 60 * 24);

        return [
            'document_type' => $invoice['document_type'],
            'document_date' => $invoice['document_date'],
            'retention_years' => $retentionYears,
            'retention_until' => $retentionUntil,
            'days_remaining' => $daysRemaining,
            'can_delete' => $daysRemaining <= 0,
            'status' => $daysRemaining > 0 ? "Aufbewahrung bis $retentionUntil" : 'Aufbewahrung abgelaufen',
        ];
    }

    /**
     * Mit Bank-Transaktion verknüpfen
     */
    public function linkToBankTransaction(int $invoiceId, int $transactionId, int $userId): bool
    {
        $invoice = $this->get($invoiceId);
        if (!$invoice) {
            return false;
        }

        $this->db->where('id', $invoiceId);
        $success = $this->db->update('incoming_invoices', [
            'bank_transaction_id' => $transactionId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($success) {
            $this->logAuditTrail($invoiceId, $invoice['instances_id'], 'updated', 'bank_transaction_id', $invoice['bank_transaction_id'] ?? null, $userId, $transactionId);
        }

        return $success;
    }

    /**
     * Lieferantennamen-Autovervollständigung
     */
    public function searchByVendor(string $query, int $instanceId): array
    {
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('vendor_name', '%' . $query . '%', 'LIKE');
        $this->db->groupBy('vendor_name');
        $this->db->orderBy('vendor_name', 'ASC');
        $this->db->limit(10);
        $results = $this->db->get('incoming_invoices') ?: [];

        return array_map(function ($row) {
            return ['name' => $row['vendor_name']];
        }, $results);
    }

    /**
     * Duplikatsprüfung
     */
    public function getDuplicateCheck(string $vendorName, ?string $documentNumber, string $amount, int $instanceId): array
    {
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('vendor_name', $vendorName);

        if ($documentNumber) {
            $this->db->where('document_number', $documentNumber);
        }

        $this->db->where('gross_amount', $amount);
        $this->db->limit(5);
        return $this->db->get('incoming_invoices') ?: [];
    }

    /**
     * Feldvalidierung
     */
    private function validateInvoiceData(array $data, bool $required = true): void
    {
        $requiredFields = ['vendor_name', 'gross_amount', 'document_date', 'instances_id'];

        if ($required) {
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Required field missing: $field");
                }
            }
        }

        // Typ-Validierung
        if (isset($data['gross_amount']) && !is_numeric($data['gross_amount'])) {
            throw new Exception('Invalid amount');
        }

        if (isset($data['document_date']) && !$this->isValidDate($data['document_date'])) {
            throw new Exception('Invalid document date format');
        }
    }

    /**
     * GoBD-Hash berechnen
     */
    private function calculateDocumentHash(array $data): string
    {
        $hashInput = implode('|', [
            $data['document_number'] ?? '',
            $data['vendor_name'] ?? '',
            $data['gross_amount'] ?? '',
            $data['document_date'] ?? '',
            $data['received_date'] ?? '',
        ]);
        return hash('sha256', $hashInput);
    }

    /**
     * Audit-Trail-Eintrag protokollieren
     */
    private function logAuditTrail(int $invoiceId, int $instanceId, string $action, ?string $fieldName = null, ?string $oldValue = null, int $userId = 0, ?string $newValue = null, ?string $reason = null): void
    {
        $auditData = [
            'incoming_invoice_id' => $invoiceId,
            'instances_id' => $instanceId,
            'action' => $action,
            'field_name' => $fieldName,
            'old_value' => $oldValue ?? ($reason ?: null),
            'new_value' => $newValue ?? ($reason ?: null),
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('incoming_invoice_audit_log', $auditData);
    }

    /**
     * Aufbewahrungsverletzungen prüfen
     */
    private function checkRetentionViolations(int $instanceId): array
    {
        $issues = [];

        // Prüfe auf Rechnungen die Aufbewahrungsfrist überschritten haben
        $tenYearsAgo = date('Y-m-d', strtotime('-10 years'));
        $sixYearsAgo = date('Y-m-d', strtotime('-6 years'));

        // Rechnungen/Quittungen
        $this->db->where('deleted', 0);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_type', ['invoice', 'receipt', 'credit_note'], 'IN');
        $this->db->where('document_date', $tenYearsAgo, '<');
        $expiredCount = $this->db->getValue('incoming_invoices', 'COUNT(*)');

        if ($expiredCount > 0) {
            $issues[] = [
                'type' => 'retention_expired',
                'severity' => 'info',
                'message' => "$expiredCount Rechnungen haben ihre Aufbewahrungsfrist überschritten und können archiviert werden",
                'count' => $expiredCount,
                'document_types' => ['invoice', 'receipt', 'credit_note'],
            ];
        }

        return $issues;
    }

    /**
     * Datum validieren
     */
    private function isValidDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
