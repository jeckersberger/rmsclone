<?php
/**
 * Angebots-Gueltigkeit und automatischer Rechnungsversand per E-Mail
 *
 * Neue Felder:
 *   - document_lifecycle.auto_send_email: Automatischer E-Mail-Versand nach Erstellung
 *   - instances.valid_until_default_days: Standard-Gueltigkeitsdauer fuer Angebote (Tage)
 *   - instances.auto_invoice_email_enabled: Automatischer Rechnungsversand aktiviert
 *   - instances.invoice_email_subject: Betreff fuer automatische Rechnungs-E-Mails
 *   - instances.invoice_email_body: Text fuer automatische Rechnungs-E-Mails
 *
 * Neue Tabelle:
 *   - document_email_log: Protokoll aller versendeten Dokument-E-Mails
 */

use Phinx\Migration\AbstractMigration;

class QuoteValidityAndAutoEmail extends AbstractMigration
{
    public function up()
    {
        // document_lifecycle: auto_send_email flag
        $dlTable = $this->table('document_lifecycle');
        if (!$dlTable->hasColumn('auto_send_email')) {
            $dlTable
                ->addColumn('auto_send_email', 'boolean', [
                    'default' => false,
                    'after' => 'notes',
                    'comment' => 'Automatischer E-Mail-Versand nach Erstellung'
                ])
                ->save();
        }

        // instances: quote validity and auto-email settings
        $instTable = $this->table('instances');
        if (!$instTable->hasColumn('valid_until_default_days')) {
            $instTable
                ->addColumn('valid_until_default_days', 'integer', [
                    'default' => 30,
                    'null' => true,
                    'comment' => 'Standard-Gueltigkeitsdauer fuer Angebote in Tagen'
                ])
                ->save();
        }
        if (!$instTable->hasColumn('auto_invoice_email_enabled')) {
            $instTable
                ->addColumn('auto_invoice_email_enabled', 'boolean', [
                    'default' => false,
                    'comment' => 'Automatischer Rechnungsversand per E-Mail aktiviert'
                ])
                ->addColumn('invoice_email_subject', 'string', [
                    'limit' => 255,
                    'null' => true,
                    'default' => null,
                    'comment' => 'Betreff-Vorlage fuer automatische Rechnungs-E-Mails'
                ])
                ->addColumn('invoice_email_body', 'text', [
                    'null' => true,
                    'default' => null,
                    'comment' => 'Text-Vorlage fuer automatische Rechnungs-E-Mails'
                ])
                ->save();
        }

        // document_email_log table
        if (!$this->hasTable('document_email_log')) {
            $this->table('document_email_log', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('document_lifecycle_id', 'integer')
                ->addColumn('recipient_email', 'string', ['limit' => 255])
                ->addColumn('subject', 'string', ['limit' => 255])
                ->addColumn('sent_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('status', 'enum', [
                    'values' => ['sent', 'failed', 'pending'],
                    'default' => 'pending',
                    'comment' => 'Versandstatus'
                ])
                ->addColumn('error_message', 'text', [
                    'null' => true,
                    'default' => null,
                    'comment' => 'Fehlermeldung bei fehlgeschlagenem Versand'
                ])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'document_lifecycle_id'])
                ->addIndex(['status'])
                ->create();
        }
    }

    public function down()
    {
        // document_lifecycle
        $dlTable = $this->table('document_lifecycle');
        if ($dlTable->hasColumn('auto_send_email')) {
            $dlTable->removeColumn('auto_send_email')->save();
        }

        // instances
        $instTable = $this->table('instances');
        foreach (['valid_until_default_days', 'auto_invoice_email_enabled',
                   'invoice_email_subject', 'invoice_email_body'] as $col) {
            if ($instTable->hasColumn($col)) {
                $instTable->removeColumn($col);
            }
        }
        $instTable->save();

        // document_email_log
        if ($this->hasTable('document_email_log')) {
            $this->table('document_email_log')->drop()->save();
        }
    }
}
