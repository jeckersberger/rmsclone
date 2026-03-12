<?php
/**
 * Phase 1 Vervollstaendigung: KUR-Uebergang, GoBD-Archivierung, Leitweg-ID, Cookie-Consent
 *
 * Neue Felder:
 *   - instances_kurTransitionYear: Jahr ab dem KUR nicht mehr gilt
 *   - instances_kurTransitionReason: Grund fuer den KUR-Uebergang
 *   - instances_cookieConsentEnabled: Cookie-Consent-Banner aktiviert
 *   - instances_privacyPolicyUrl: Link zur Datenschutzerklaerung
 *   - instances_imprintUrl: Link zum Impressum
 *   - clients_leitwegId: Leitweg-ID fuer oeffentliche Auftraggeber (XRechnung)
 *   - document_exports: archive_status und retention_expires_at fuer GoBD-Archivierung
 */

use Phinx\Migration\AbstractMigration;

class Phase1Completion extends AbstractMigration
{
    public function change()
    {
        // KUR-Uebergangsfelder
        if ($this->table('instances')->hasColumn('instances_kurEnabled')) {
            if (!$this->table('instances')->hasColumn('instances_kurTransitionYear')) {
                $this->table('instances')
                    ->addColumn('instances_kurTransitionYear', 'integer', ['null' => true, 'default' => null, 'after' => 'instances_kurEnabled', 'comment' => 'Jahr ab dem KUR nicht mehr gilt (automatisch bei Grenzueberschreitung)'])
                    ->addColumn('instances_kurTransitionReason', 'string', ['limit' => 500, 'null' => true, 'default' => null, 'after' => 'instances_kurTransitionYear', 'comment' => 'Grund fuer KUR-Uebergang'])
                    ->update();
            }
        }

        // Cookie-Consent und Rechtliches
        if (!$this->table('instances')->hasColumn('instances_cookieConsentEnabled')) {
            $this->table('instances')
                ->addColumn('instances_cookieConsentEnabled', 'boolean', ['default' => false, 'comment' => 'Cookie-Consent-Banner aktiviert'])
                ->addColumn('instances_privacyPolicyUrl', 'string', ['limit' => 500, 'null' => true, 'default' => null, 'comment' => 'URL zur Datenschutzerklaerung'])
                ->addColumn('instances_imprintUrl', 'string', ['limit' => 500, 'null' => true, 'default' => null, 'comment' => 'URL zum Impressum'])
                ->addColumn('instances_privacyPolicyHtml', 'text', ['null' => true, 'default' => null, 'comment' => 'Inline-Datenschutzerklaerung HTML'])
                ->addColumn('instances_imprintHtml', 'text', ['null' => true, 'default' => null, 'comment' => 'Inline-Impressum HTML'])
                ->update();
        }

        // Leitweg-ID fuer XRechnung (oeffentliche Auftraggeber)
        if (!$this->table('clients')->hasColumn('clients_leitwegId')) {
            $this->table('clients')
                ->addColumn('clients_leitwegId', 'string', ['limit' => 50, 'null' => true, 'default' => null, 'comment' => 'Leitweg-ID fuer XRechnung (oeffentliche Auftraggeber)'])
                ->addColumn('clients_buyerReference', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'Bestellreferenz des Kunden'])
                ->update();
        }

        // GoBD-Archivierung: Felder in document_exports
        if ($this->hasTable('document_exports')) {
            if (!$this->table('document_exports')->hasColumn('archive_status')) {
                $this->table('document_exports')
                    ->addColumn('archive_status', 'enum', ['values' => ['active', 'archived', 'retention_expired'], 'default' => 'active', 'comment' => 'GoBD Archivstatus'])
                    ->addColumn('retention_expires_at', 'datetime', ['null' => true, 'default' => null, 'comment' => 'Aufbewahrungsfrist endet (10 Jahre nach Erstellung)'])
                    ->addColumn('archived_at', 'datetime', ['null' => true, 'default' => null, 'comment' => 'Zeitpunkt der Archivierung'])
                    ->addIndex(['archive_status'])
                    ->addIndex(['retention_expires_at'])
                    ->update();

                // Bestehende Dokumente: retention_expires_at setzen (10 Jahre)
                $this->execute("UPDATE document_exports SET retention_expires_at = DATE_ADD(generated_at, INTERVAL 10 YEAR) WHERE retention_expires_at IS NULL AND generated_at IS NOT NULL");
            }
        }

        // Nummernkreis-Sicherheit: sequence_log fuer lueckenlose Dokumentation
        if (!$this->hasTable('document_sequence_log')) {
            $this->table('document_sequence_log', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('sequence_type', 'string', ['limit' => 30, 'comment' => 'invoice, quote, delivery_note'])
                ->addColumn('doc_number', 'string', ['limit' => 64, 'comment' => 'Generierte Dokumentnummer'])
                ->addColumn('sequence_value', 'integer', ['comment' => 'Laufende Nummer zum Zeitpunkt'])
                ->addColumn('generated_by', 'integer', ['null' => true, 'comment' => 'User-ID'])
                ->addColumn('generated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('used', 'boolean', ['default' => true, 'comment' => 'Wurde die Nummer tatsaechlich verwendet'])
                ->addColumn('void_reason', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Grund falls Nummer storniert (nie loeschen, nur markieren)'])
                ->addIndex(['instances_id', 'sequence_type', 'sequence_value'], ['unique' => true])
                ->addIndex(['instances_id', 'doc_number'], ['unique' => true])
                ->create();
        }

        // DSGVO-Report-Protokoll
        if (!$this->hasTable('dsgvo_reports')) {
            $this->table('dsgvo_reports', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('report_year', 'integer')
                ->addColumn('report_type', 'enum', ['values' => ['annual', 'avv', 'retention_check']])
                ->addColumn('s3files_id', 'integer', ['null' => true, 'comment' => 'Generiertes PDF'])
                ->addColumn('generated_by', 'integer', ['null' => true])
                ->addColumn('generated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('details_json', 'text', ['null' => true])
                ->addIndex(['instances_id', 'report_year', 'report_type'])
                ->create();
        }

        // Cookie-Consent-Log
        if (!$this->hasTable('cookie_consents')) {
            $this->table('cookie_consents', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('session_id', 'string', ['limit' => 128])
                ->addColumn('users_userid', 'integer', ['null' => true])
                ->addColumn('consent_given', 'boolean', ['default' => false])
                ->addColumn('consent_categories', 'string', ['limit' => 255, 'default' => 'necessary', 'comment' => 'Komma-getrennt: necessary,analytics,marketing'])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('consented_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('expires_at', 'datetime', ['null' => true, 'comment' => 'Consent laeuft nach 12 Monaten ab'])
                ->addIndex(['instances_id', 'session_id'])
                ->addIndex(['expires_at'])
                ->create();
        }
    }
}
