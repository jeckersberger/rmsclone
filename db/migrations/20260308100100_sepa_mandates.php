<?php
/**
 * SEPA-Lastschrift-Mandatsverwaltung
 *
 * Neue Tabelle:
 *   sepa_mandates - SEPA-Lastschriftmandate fuer Kunden
 *
 * Neue Spalte:
 *   clients.clients_sepaMandate - Kennzeichen ob Kunde ein SEPA-Mandat hat
 */

use Phinx\Migration\AbstractMigration;

class SepaMandates extends AbstractMigration
{
    public function change()
    {
        // SEPA-Mandate Tabelle
        if (!$this->hasTable('sepa_mandates')) {
            $table = $this->table('sepa_mandates');
            $table
                ->addColumn('clients_id', 'integer', ['null' => false, 'comment' => 'FK zu clients'])
                ->addColumn('instances_id', 'integer', ['null' => false, 'comment' => 'FK zu instances'])
                ->addColumn('mandate_reference', 'string', ['limit' => 35, 'null' => false, 'comment' => 'Eindeutige Mandatsreferenz (z.B. MNDT-2026-0001)'])
                ->addColumn('mandate_date', 'date', ['null' => false, 'comment' => 'Datum der Mandatserteilung'])
                ->addColumn('iban', 'string', ['limit' => 34, 'null' => false, 'comment' => 'IBAN des Zahlungspflichtigen'])
                ->addColumn('bic', 'string', ['limit' => 11, 'null' => true, 'default' => null, 'comment' => 'BIC des Zahlungspflichtigen'])
                ->addColumn('account_holder', 'string', ['limit' => 140, 'null' => false, 'comment' => 'Name des Kontoinhabers'])
                ->addColumn('mandate_type', 'enum', ['values' => ['CORE', 'B2B'], 'default' => 'CORE', 'comment' => 'Mandatstyp: CORE (Verbraucher) oder B2B (Geschaeftskunden)'])
                ->addColumn('status', 'enum', ['values' => ['active', 'revoked', 'expired'], 'default' => 'active', 'comment' => 'Mandatsstatus'])
                ->addColumn('signed_at', 'datetime', ['null' => true, 'default' => null, 'comment' => 'Zeitpunkt der Unterschrift'])
                ->addColumn('revoked_at', 'datetime', ['null' => true, 'default' => null, 'comment' => 'Zeitpunkt der Widerrufung'])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'comment' => 'Erstellungszeitpunkt'])
                ->addIndex(['mandate_reference'], ['unique' => true, 'name' => 'idx_sepa_mandate_reference'])
                ->addIndex(['clients_id'], ['name' => 'idx_sepa_clients_id'])
                ->addIndex(['instances_id'], ['name' => 'idx_sepa_instances_id'])
                ->addIndex(['status'], ['name' => 'idx_sepa_status'])
                ->create();
        }

        // Kennzeichen in clients-Tabelle
        if (!$this->table('clients')->hasColumn('clients_sepaMandate')) {
            $this->table('clients')
                ->addColumn('clients_sepaMandate', 'boolean', ['default' => false, 'comment' => 'Kunde hat ein aktives SEPA-Lastschriftmandat'])
                ->update();
        }
    }
}
