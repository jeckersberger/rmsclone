<?php
/**
 * Zahlungseingaenge mit Bankdaten abgleichen (MT940/CAMT Import)
 *
 * Tabellen:
 * - bank_transactions: Einzelne Transaktionen aus Kontoauszuegen
 * - bank_import_log: Protokoll aller Import-Vorgaenge
 */
use Phinx\Migration\AbstractMigration;

class BankImportMatching extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) BANK_TRANSACTIONS ═══════
        if (!$this->hasTable('bank_transactions')) {
            $this->table('bank_transactions', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('transaction_date', 'date')
                ->addColumn('value_date', 'date', ['null' => true])
                ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
                ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'EUR'])
                ->addColumn('sender_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('sender_iban', 'string', ['limit' => 34, 'null' => true])
                ->addColumn('reference', 'text', ['null' => true, 'comment' => 'Verwendungszweck'])
                ->addColumn('booking_text', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('matched_document_id', 'integer', ['null' => true, 'comment' => 'FK zu document_exports'])
                ->addColumn('match_status', 'enum', [
                    'values' => ['unmatched', 'auto_matched', 'manual_matched', 'ignored'],
                    'default' => 'unmatched'
                ])
                ->addColumn('import_batch', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['matched_document_id'])
                ->addIndex(['match_status'])
                ->addIndex(['import_batch'])
                ->addIndex(['transaction_date'])
                ->addForeignKey('matched_document_id', 'document_exports', 'document_exports_id', [
                    'delete' => 'SET_NULL',
                    'update' => 'CASCADE'
                ])
                ->create();
        }

        // ═══════ 2) BANK_IMPORT_LOG ═══════
        if (!$this->hasTable('bank_import_log')) {
            $this->table('bank_import_log', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('filename', 'string', ['limit' => 255])
                ->addColumn('format', 'enum', ['values' => ['mt940', 'camt053', 'csv']])
                ->addColumn('records_imported', 'integer', ['default' => 0])
                ->addColumn('records_matched', 'integer', ['default' => 0])
                ->addColumn('imported_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('bank_transactions')->drop()->save();
        $this->table('bank_import_log')->drop()->save();
    }
}
