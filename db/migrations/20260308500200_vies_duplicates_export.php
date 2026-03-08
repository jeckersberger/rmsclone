<?php
/**
 * VIES-Validierung, Kunden-Duplikate und Buchhaltungs-Export
 *
 * Tabellen fuer:
 * - VIES USt-IdNr. Validierungs-Cache
 * - Kunden-Zusammenfuehrungsprotokoll
 */
use Phinx\Migration\AbstractMigration;

class ViesDuplicatesExport extends AbstractMigration
{
    public function up()
    {
        // VIES Validierungs-Cache
        if (!$this->hasTable('vies_validation_cache')) {
            $this->table('vies_validation_cache', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('vat_id', 'string', ['limit' => 20])
                ->addColumn('country_code', 'string', ['limit' => 2])
                ->addColumn('is_valid', 'boolean', ['default' => 0])
                ->addColumn('company_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('company_address', 'text', ['null' => true])
                ->addColumn('validated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('expires_at', 'datetime')
                ->addIndex(['vat_id'], ['unique' => false])
                ->addIndex(['vat_id', 'country_code'], ['unique' => false])
                ->addIndex(['expires_at'])
                ->create();
        }

        // Kunden-Zusammenfuehrungsprotokoll
        if (!$this->hasTable('client_merge_log')) {
            $this->table('client_merge_log', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('source_client_id', 'integer')
                ->addColumn('target_client_id', 'integer')
                ->addColumn('merged_by', 'integer')
                ->addColumn('merged_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('merge_details', 'text', ['null' => true, 'comment' => 'JSON mit Details der Zusammenfuehrung'])
                ->addIndex(['instances_id'])
                ->addIndex(['source_client_id'])
                ->addIndex(['target_client_id'])
                ->addIndex(['merged_by'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('vies_validation_cache')->drop()->save();
        $this->table('client_merge_log')->drop()->save();
    }
}
