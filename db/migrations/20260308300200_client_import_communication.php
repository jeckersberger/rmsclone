<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Kunden-Import aus CSV/Excel und Kommunikationsprotokoll
 *
 * Neue Tabellen:
 *   - client_import_log: Protokoll aller CSV/Excel-Importvorgaenge
 *   - client_communications: Kommunikationsprotokoll (E-Mail, Telefon, Meeting, Notiz, Brief)
 */
final class ClientImportCommunication extends AbstractMigration
{
    public function change(): void
    {
        // ═══════════════════════════════════════════════
        //  client_import_log
        // ═══════════════════════════════════════════════
        if (!$this->hasTable('client_import_log')) {
            $this->table('client_import_log', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('filename', 'string', ['limit' => 255, 'comment' => 'Originaler Dateiname'])
                ->addColumn('records_total', 'integer', ['default' => 0, 'comment' => 'Gesamtanzahl Datensaetze in Datei'])
                ->addColumn('records_imported', 'integer', ['default' => 0, 'comment' => 'Erfolgreich importierte Datensaetze'])
                ->addColumn('records_skipped', 'integer', ['default' => 0, 'comment' => 'Uebersprungene Duplikate'])
                ->addColumn('errors', 'text', ['null' => true, 'default' => null, 'comment' => 'Fehlermeldungen (JSON)'])
                ->addColumn('imported_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('imported_by', 'integer', ['comment' => 'User-ID des Importierenden'])
                ->addIndex(['instances_id'])
                ->addIndex(['imported_at'])
                    'delete' => 'CASCADE',
                    'update' => 'NO_ACTION',
                ])
                ->create();
        }

        // ═══════════════════════════════════════════════
        //  client_communications
        // ═══════════════════════════════════════════════
        if (!$this->hasTable('client_communications')) {
            $this->table('client_communications', ['id' => true, 'primary_key' => 'id'])
                ->addColumn('clients_id', 'integer')
                ->addColumn('type', 'enum', [
                    'values' => ['email', 'phone', 'meeting', 'note', 'letter'],
                    'comment' => 'Art der Kommunikation',
                ])
                ->addColumn('subject', 'string', ['limit' => 255, 'comment' => 'Betreff'])
                ->addColumn('content', 'text', ['null' => true, 'default' => null, 'comment' => 'Inhalt/Notizen'])
                ->addColumn('contact_person', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Ansprechpartner'])
                ->addColumn('direction', 'enum', [
                    'values' => ['inbound', 'outbound'],
                    'comment' => 'Richtung: eingehend/ausgehend',
                ])
                ->addColumn('communication_date', 'datetime', ['comment' => 'Zeitpunkt der Kommunikation'])
                ->addColumn('created_by', 'integer', ['comment' => 'Erstellt von (User-ID)'])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['clients_id'])
                ->addIndex(['communication_date'])
                ->addIndex(['type'])
                    'delete' => 'CASCADE',
                    'update' => 'NO_ACTION',
                ])
                ->create();
        }
    }
}
