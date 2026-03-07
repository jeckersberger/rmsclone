<?php
/**
 * Bank Import - Kontoauszug-Import (MT940/CAMT.053)
 *
 * Tabellen:
 * - bank_accounts: Konfigurierbare Bankkonten pro Instanz
 * - bank_import_sessions: Import-Sitzungen (ein Upload = eine Session)
 * - bank_transactions: Einzelne Transaktionen aus Kontoauszuegen
 */
use Phinx\Migration\AbstractMigration;

class BankImport extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) BANKKONTEN (frei konfigurierbar, nicht bank-spezifisch) ═══════
        if (!$this->hasTable('bank_accounts')) {
            $this->table('bank_accounts', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('account_name', 'string', ['limit' => 100, 'comment' => 'Freitext-Name, z.B. "Sparkasse Geschaeftskonto"'])
                ->addColumn('iban', 'string', ['limit' => 34, 'null' => true])
                ->addColumn('bic', 'string', ['limit' => 11, 'null' => true])
                ->addColumn('bank_name', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'EUR'])
                ->addColumn('is_default', 'boolean', ['default' => false])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['iban'])
                ->create();
        }

        // ═══════ 2) IMPORT-SESSIONS ═══════
        if (!$this->hasTable('bank_import_sessions')) {
            $this->table('bank_import_sessions', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('bank_accounts_id', 'integer', ['null' => true])
                ->addColumn('format', 'string', ['limit' => 20, 'comment' => 'mt940, camt053, csv'])
                ->addColumn('filename', 'string', ['limit' => 255])
                ->addColumn('s3files_id', 'integer', ['null' => true, 'comment' => 'Original-Datei als Archiv'])
                ->addColumn('statement_date', 'date', ['null' => true])
                ->addColumn('opening_balance', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('closing_balance', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true])
                ->addColumn('transaction_count', 'integer', ['default' => 0])
                ->addColumn('matched_count', 'integer', ['default' => 0])
                ->addColumn('confirmed', 'boolean', ['default' => false, 'comment' => 'Alle Zuordnungen bestaetigt'])
                ->addColumn('imported_by', 'integer')
                ->addColumn('confirmed_by', 'integer', ['null' => true])
                ->addColumn('confirmed_at', 'datetime', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['bank_accounts_id'])
                ->create();
        }

        // ═══════ 3) TRANSAKTIONEN ═══════
        if (!$this->hasTable('bank_transactions')) {
            $this->table('bank_transactions', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('bank_import_sessions_id', 'integer')
                ->addColumn('transaction_date', 'date')
                ->addColumn('value_date', 'date', ['null' => true])
                ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'comment' => 'Positiv = Eingang, Negativ = Ausgang'])
                ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'EUR'])
                ->addColumn('counterpart_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('counterpart_iban', 'string', ['limit' => 34, 'null' => true])
                ->addColumn('reference', 'text', ['null' => true, 'comment' => 'Verwendungszweck'])
                ->addColumn('booking_text', 'string', ['limit' => 100, 'null' => true, 'comment' => 'z.B. GUTSCHR, LASTSCHR, UEBERWEIS'])
                ->addColumn('end_to_end_id', 'string', ['limit' => 100, 'null' => true, 'comment' => 'SEPA End-to-End-ID'])
                ->addColumn('mandate_reference', 'string', ['limit' => 100, 'null' => true])
                // Matching
                ->addColumn('match_status', 'string', ['limit' => 20, 'default' => 'unmatched', 'comment' => 'unmatched, suggested, confirmed, ignored'])
                ->addColumn('document_lifecycle_id', 'integer', ['null' => true, 'comment' => 'Zugeordnete Rechnung'])
                ->addColumn('match_confidence', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'comment' => 'Auto-Match Konfidenz 0-100'])
                ->addColumn('match_method', 'string', ['limit' => 30, 'null' => true, 'comment' => 'auto_reference, auto_amount, manual'])
                ->addColumn('matched_by', 'integer', ['null' => true])
                ->addColumn('matched_at', 'datetime', ['null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('duplicate_hash', 'string', ['limit' => 64, 'null' => true, 'comment' => 'SHA-256 fuer Duplikaterkennung'])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['bank_import_sessions_id'])
                ->addIndex(['document_lifecycle_id'])
                ->addIndex(['match_status'])
                ->addIndex(['duplicate_hash'])
                ->addIndex(['transaction_date'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('bank_transactions')->drop()->save();
        $this->table('bank_import_sessions')->drop()->save();
        $this->table('bank_accounts')->drop()->save();
    }
}
