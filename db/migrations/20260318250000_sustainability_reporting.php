<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * Sustainability Reporting & ESG Module
 *
 * Creates tables for tracking:
 * - Configuration per instance (emission factors, energy costs)
 * - Transport emissions logging
 * - Energy consumption logging
 * - ESG reports (monthly, quarterly, annual, project-based)
 */
final class SustainabilityReporting extends AbstractMigration
{
    public function change(): void
    {
        // Configuration table: emission factors and energy pricing
        $this->table('sustainability_config', ['id' => false, 'primary_key' => ['instances_id']])
            ->addColumn('instances_id', 'integer', ['null' => false])
            ->addColumn('emission_factors', 'json', [
                'null' => false,
                'default' => '{"van_per_km":0.21,"truck_per_km":0.35,"car_per_km":0.15}',
                'comment' => 'CO2 emission factors in kg per km for different vehicle types'
            ])
            ->addColumn('kwh_price_eur', 'decimal', [
                'precision' => 8,
                'scale' => 4,
                'default' => 0.30,
                'comment' => 'Average electricity price in EUR per kWh'
            ])
            ->addColumn('co2_per_kwh', 'decimal', [
                'precision' => 8,
                'scale' => 4,
                'default' => 0.42,
                'comment' => 'CO2 emissions in kg per kWh of electricity'
            ])
            ->addColumn('enable_client_reports', 'boolean', [
                'null' => false,
                'default' => 0,
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => 'Whether to generate sustainability reports for clients'
            ])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'
            ])
            ->create();

        // Transport emissions logging
        $this->table('sustainability_transport_log')
            ->addColumn('tour_id', 'integer', ['null' => true, 'comment' => 'Transport tour ID'])
            ->addColumn('project_id', 'integer', ['null' => true, 'comment' => 'Project ID if multi-modal transport'])
            ->addColumn('distance_km', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => false,
                'comment' => 'Distance traveled in kilometers'
            ])
            ->addColumn('vehicle_type', 'string', [
                'length' => 50,
                'null' => false,
                'comment' => 'Type of vehicle (van, truck, car, etc.)'
            ])
            ->addColumn('co2_kg', 'decimal', [
                'precision' => 10,
                'scale' => 4,
                'null' => false,
                'comment' => 'Calculated CO2 emissions in kg'
            ])
            ->addColumn('instances_id', 'integer', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id', 'created_at'])
            ->addIndex(['project_id', 'instances_id'])
            ->create();

        // Energy consumption logging
        $this->table('sustainability_energy_log')
            ->addColumn('project_id', 'integer', ['null' => false, 'comment' => 'Project ID'])
            ->addColumn('total_kwh', 'decimal', [
                'precision' => 12,
                'scale' => 2,
                'null' => false,
                'comment' => 'Total electricity consumption in kWh'
            ])
            ->addColumn('co2_kg', 'decimal', [
                'precision' => 10,
                'scale' => 4,
                'null' => false,
                'comment' => 'Calculated CO2 emissions from energy'
            ])
            ->addColumn('calculation_basis', 'text', [
                'null' => true,
                'comment' => 'Description of how energy was calculated (e.g., equipment list with hours)'
            ])
            ->addColumn('instances_id', 'integer', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id', 'created_at'])
            ->addIndex(['project_id', 'instances_id'])
            ->create();

        // ESG reports
        $this->table('sustainability_reports')
            ->addColumn('name', 'string', [
                'length' => 255,
                'null' => false,
                'comment' => 'Report name/title'
            ])
            ->addColumn('report_type', 'enum', [
                'values' => ['monthly', 'quarterly', 'annual', 'project', 'client'],
                'null' => false,
                'comment' => 'Type of sustainability report'
            ])
            ->addColumn('period_start', 'date', [
                'null' => false,
                'comment' => 'Report period start date'
            ])
            ->addColumn('period_end', 'date', [
                'null' => false,
                'comment' => 'Report period end date'
            ])
            ->addColumn('data_json', 'longtext', [
                'null' => false,
                'comment' => 'Report data as JSON (metrics, calculations, trends)'
            ])
            ->addColumn('pdf_path', 'string', [
                'length' => 500,
                'null' => true,
                'comment' => 'Path to generated PDF file if available'
            ])
            ->addColumn('instances_id', 'integer', ['null' => false])
            ->addColumn('created_by', 'integer', [
                'null' => false,
                'comment' => 'User ID who generated report'
            ])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id', 'report_type', 'created_at'])
            ->addIndex(['instances_id', 'period_start', 'period_end'])
            ->create();
    }
}
