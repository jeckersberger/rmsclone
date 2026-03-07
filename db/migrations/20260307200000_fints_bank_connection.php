<?php
/**
 * FinTS/HBCI Bankanbindung
 *
 * Erweitert bank_accounts um FinTS-Zugangsdaten und fuegt eine Tabelle
 * fuer laufende TAN-Dialoge hinzu.
 */
use Phinx\Migration\AbstractMigration;

class FinTSBankConnection extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) FinTS-Felder auf bank_accounts ═══════
        $table = $this->table('bank_accounts');
        if (!$table->hasColumn('fints_url')) {
            $table
                ->addColumn('fints_url', 'string', ['limit' => 500, 'null' => true, 'after' => 'bic', 'comment' => 'FinTS/HBCI Server-URL'])
                ->addColumn('fints_port', 'integer', ['null' => true, 'after' => 'fints_url', 'default' => 443])
                ->addColumn('fints_version', 'string', ['limit' => 10, 'null' => true, 'after' => 'fints_port', 'default' => '300', 'comment' => '300=FinTS 3.0'])
                ->addColumn('fints_username', 'string', ['limit' => 255, 'null' => true, 'after' => 'fints_version', 'comment' => 'Online-Banking Benutzerkennung'])
                ->addColumn('fints_blz', 'string', ['limit' => 8, 'null' => true, 'after' => 'fints_username', 'comment' => 'Bankleitzahl'])
                ->addColumn('fints_account_number', 'string', ['limit' => 34, 'null' => true, 'after' => 'fints_blz', 'comment' => 'Kontonummer oder IBAN fuer FinTS-Abfrage'])
                ->addColumn('fints_enabled', 'boolean', ['default' => false, 'after' => 'fints_account_number'])
                ->addColumn('fints_last_sync', 'datetime', ['null' => true, 'after' => 'fints_enabled', 'comment' => 'Letzte erfolgreiche Synchronisation'])
                ->addColumn('fints_last_sync_date', 'date', ['null' => true, 'after' => 'fints_last_sync', 'comment' => 'Bis zu welchem Datum synchronisiert wurde'])
                ->save();
        }

        // ═══════ 2) TAN-Dialoge (kurzlebig, fuer interaktive TAN-Eingabe) ═══════
        if (!$this->hasTable('fints_tan_sessions')) {
            $this->table('fints_tan_sessions', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('bank_accounts_id', 'integer')
                ->addColumn('user_id', 'integer')
                ->addColumn('action', 'string', ['limit' => 50, 'comment' => 'fetch_transactions, get_balance, get_accounts'])
                ->addColumn('action_params', 'text', ['null' => true, 'comment' => 'JSON: Aktions-Parameter (z.B. Zeitraum)'])
                ->addColumn('persist_data', 'text', ['null' => true, 'comment' => 'Serialisierter FinTS-Dialog-State'])
                ->addColumn('tan_medium', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Gewaehltes TAN-Medium'])
                ->addColumn('tan_mechanism', 'string', ['limit' => 50, 'null' => true, 'comment' => 'TAN-Verfahren ID'])
                ->addColumn('challenge_text', 'text', ['null' => true, 'comment' => 'TAN-Challenge Text fuer den User'])
                ->addColumn('challenge_image', 'text', ['null' => true, 'comment' => 'Base64-encoded photoTAN/QR Bild'])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending', 'comment' => 'pending, awaiting_tan, completed, failed, expired'])
                ->addColumn('result_data', 'text', ['null' => true, 'comment' => 'JSON: Ergebnis der Aktion'])
                ->addColumn('error_message', 'text', ['null' => true])
                ->addColumn('expires_at', 'datetime', ['comment' => 'TAN-Sessions laufen nach 5 Min ab'])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('completed_at', 'datetime', ['null' => true])
                ->addIndex(['instances_id'])
                ->addIndex(['bank_accounts_id'])
                ->addIndex(['status'])
                ->addIndex(['expires_at'])
                ->create();
        }

        // ═══════ 3) FinTS-Sync-Log (wann wurde was abgerufen) ═══════
        if (!$this->hasTable('fints_sync_log')) {
            $this->table('fints_sync_log', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('bank_accounts_id', 'integer')
                ->addColumn('user_id', 'integer')
                ->addColumn('action', 'string', ['limit' => 50])
                ->addColumn('date_from', 'date', ['null' => true])
                ->addColumn('date_to', 'date', ['null' => true])
                ->addColumn('transactions_fetched', 'integer', ['default' => 0])
                ->addColumn('transactions_new', 'integer', ['default' => 0])
                ->addColumn('transactions_duplicate', 'integer', ['default' => 0])
                ->addColumn('bank_import_sessions_id', 'integer', ['null' => true, 'comment' => 'Verknuepfte Import-Session'])
                ->addColumn('success', 'boolean', ['default' => true])
                ->addColumn('error_message', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['bank_accounts_id'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('fints_sync_log')->drop()->save();
        $this->table('fints_tan_sessions')->drop()->save();

        $table = $this->table('bank_accounts');
        foreach (['fints_url', 'fints_port', 'fints_version', 'fints_username', 'fints_blz',
                   'fints_account_number', 'fints_enabled', 'fints_last_sync', 'fints_last_sync_date'] as $col) {
            if ($table->hasColumn($col)) {
                $table->removeColumn($col);
            }
        }
        $table->save();
    }
}
