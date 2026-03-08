<?php
/**
 * Reporting Features - Equipment Utilization Cache
 *
 * Zwischenspeicher fuer Auslastungsdaten zur schnellen Abfrage
 * in Berichten und Dashboards.
 */
use Phinx\Migration\AbstractMigration;

class ReportingFeatures extends AbstractMigration
{
    public function up()
    {
        if (!$this->hasTable('equipment_utilization_cache')) {
            $this->table('equipment_utilization_cache', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('assetTypes_id', 'integer')
                ->addColumn('year', 'integer')
                ->addColumn('month', 'integer')
                ->addColumn('days_rented', 'integer', ['default' => 0])
                ->addColumn('days_available', 'integer', ['default' => 0])
                ->addColumn('revenue', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('utilization_pct', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0])
                ->addColumn('calculated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['assetTypes_id', 'year', 'month'], ['unique' => true])
                ->addIndex(['year', 'month'])
                ->addIndex(['assetTypes_id'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('equipment_utilization_cache')->drop()->save();
    }
}
