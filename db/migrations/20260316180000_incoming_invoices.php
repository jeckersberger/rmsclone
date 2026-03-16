<?php
use Phinx\Migration\AbstractMigration;

class IncomingInvoices extends AbstractMigration
{
    public function up()
    {
        // Main incoming invoices table
        if (!$this->hasTable('incoming_invoices')) {
            $this->table('incoming_invoices', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('document_type', 'enum', ['values' => ['invoice', 'receipt', 'credit_note', 'expense_report', 'delivery_note'], 'default' => 'invoice'])
                ->addColumn('document_number', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('vendor_name', 'string', ['limit' => 255])
                ->addColumn('vendor_vat_id', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('gross_amount', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('net_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('vat_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('vat_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
                ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'EUR'])
                ->addColumn('document_date', 'date')
                ->addColumn('received_date', 'date')
                ->addColumn('due_date', 'date', ['null' => true])
                ->addColumn('paid_date', 'date', ['null' => true])
                ->addColumn('payment_method', 'enum', ['values' => ['bank_transfer', 'cash', 'card', 'paypal', 'other'], 'null' => true])
                ->addColumn('payment_reference', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('bank_transaction_id', 'integer', ['null' => true])
                ->addColumn('category_id', 'integer', ['null' => true])
                ->addColumn('project_id', 'integer', ['null' => true])
                ->addColumn('cost_center', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('tax_deductible', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
                ->addColumn('status', 'enum', ['values' => ['draft', 'recorded', 'verified', 'paid', 'disputed', 'cancelled'], 'default' => 'draft'])
                ->addColumn('recorded_by', 'integer', ['signed' => true])
                ->addColumn('verified_by', 'integer', ['null' => true, 'signed' => true])
                ->addColumn('verified_at', 'timestamp', ['null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('gobd_hash', 'string', ['limit' => 64, 'null' => true])
                ->addColumn('gobd_recorded_at', 'timestamp', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addColumn('deleted', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                ->addColumn('deleted_at', 'timestamp', ['null' => true])
                ->addIndex(['instances_id'])
                ->addIndex(['vendor_name'])
                ->addIndex(['document_date'])
                ->addIndex(['status'])
                ->addIndex(['project_id'])
                ->addIndex(['bank_transaction_id'])
                ->addIndex(['category_id'])
                ->create();
        }

        // File attachments for invoices
        if (!$this->hasTable('incoming_invoice_files')) {
            $this->table('incoming_invoice_files', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('incoming_invoice_id', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('s3files_id', 'integer', ['signed' => true])
                ->addColumn('file_type', 'enum', ['values' => ['original', 'scan', 'photo', 'attachment'], 'default' => 'original'])
                ->addColumn('page_number', 'integer', ['null' => true])
                ->addColumn('gobd_hash', 'string', ['limit' => 64])
                ->addColumn('uploaded_by', 'integer', ['signed' => true])
                ->addColumn('uploaded_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['incoming_invoice_id'])
                ->addIndex(['instances_id'])
                ->addIndex(['s3files_id'])
                ->create();
        }

        // Audit trail for GoBD compliance
        if (!$this->hasTable('incoming_invoice_audit_log')) {
            $this->table('incoming_invoice_audit_log', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('incoming_invoice_id', 'integer', ['signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('action', 'enum', ['values' => ['created', 'updated', 'verified', 'paid', 'cancelled', 'file_added', 'file_removed', 'status_changed', 'reopened']])
                ->addColumn('field_name', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('old_value', 'text', ['null' => true])
                ->addColumn('new_value', 'text', ['null' => true])
                ->addColumn('user_id', 'integer', ['signed' => true])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['incoming_invoice_id'])
                ->addIndex(['instances_id'])
                ->addIndex(['created_at'])
                ->create();
        }

        // Expense categories for invoices
        if (!$this->hasTable('expense_categories')) {
            $this->table('expense_categories', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('skr03_account', 'string', ['limit' => 10, 'null' => true])
                ->addColumn('skr04_account', 'string', ['limit' => 10, 'null' => true])
                ->addColumn('parent_id', 'integer', ['null' => true])
                ->addColumn('is_system', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'name'], ['unique' => true])
                ->addIndex(['instances_id'])
                ->create();
        }

        // GoBD retention configuration
        if (!$this->hasTable('gobd_retention_config')) {
            $this->table('gobd_retention_config', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer', ['signed' => true])
                ->addColumn('document_type', 'string', ['limit' => 50])
                ->addColumn('retention_years', 'integer', ['default' => 10])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'document_type'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('gobd_retention_config')->drop()->save();
        $this->table('incoming_invoice_audit_log')->drop()->save();
        $this->table('incoming_invoice_files')->drop()->save();
        $this->table('expense_categories')->drop()->save();
        $this->table('incoming_invoices')->drop()->save();
    }
}
