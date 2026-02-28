<?php
/**
 * Phase 3 Migration: ZUGFeRD, Reports, Packlisten, Wiederkehrende Rechnungen,
 * Check-in/Check-out
 */
use Phinx\Migration\AbstractMigration;

class Phase3ZugferdReportsLogistics extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) PACKLISTEN & LOGISTIK ═══════
        if (!$this->hasTable('packing_lists')) {
            $this->table('packing_lists', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('projects_id', 'integer')
                ->addColumn('list_number', 'string', ['limit' => 50])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('status', 'string', ['limit' => 30, 'default' => 'draft', 'comment' => 'draft, packing, packed, dispatched, returned'])
                ->addColumn('vehicle', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('driver', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('dispatch_date', 'datetime', ['null' => true])
                ->addColumn('return_date', 'datetime', ['null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('total_weight', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('total_items', 'integer', ['default' => 0])
                ->addColumn('s3files_id', 'integer', ['null' => true, 'comment' => 'PDF'])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'projects_id'])
                ->create();
        }

        if (!$this->hasTable('packing_list_items')) {
            $this->table('packing_list_items', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('packing_lists_id', 'integer')
                ->addColumn('assets_id', 'integer', ['null' => true])
                ->addColumn('assetsAssignments_id', 'integer', ['null' => true])
                ->addColumn('item_name', 'string', ['limit' => 255])
                ->addColumn('quantity', 'integer', ['default' => 1])
                ->addColumn('weight', 'decimal', ['precision' => 8, 'scale' => 2, 'default' => 0])
                ->addColumn('packed', 'boolean', ['default' => false])
                ->addColumn('packed_by', 'integer', ['null' => true])
                ->addColumn('packed_at', 'datetime', ['null' => true])
                ->addColumn('case_label', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Case/Flightcase Zuordnung'])
                ->addColumn('notes', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addIndex(['packing_lists_id'])
                ->create();
        }

        // ═══════ 2) WIEDERKEHRENDE RECHNUNGEN ═══════
        if (!$this->hasTable('recurring_invoices')) {
            $this->table('recurring_invoices', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('clients_id', 'integer')
                ->addColumn('projects_id', 'integer', ['null' => true])
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('interval_type', 'string', ['limit' => 20, 'comment' => 'weekly, monthly, quarterly, yearly'])
                ->addColumn('interval_count', 'integer', ['default' => 1])
                ->addColumn('next_date', 'date')
                ->addColumn('end_date', 'date', ['null' => true])
                ->addColumn('template_key', 'string', ['limit' => 80, 'null' => true])
                ->addColumn('net_amount', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('lines_json', 'text', ['comment' => 'JSON array of line items'])
                ->addColumn('is_active', 'boolean', ['default' => true])
                ->addColumn('last_generated_at', 'datetime', ['null' => true])
                ->addColumn('total_generated', 'integer', ['default' => 0])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'next_date'])
                ->addIndex(['clients_id'])
                ->create();
        }

        // ═══════ 3) CHECK-IN / CHECK-OUT MIT ZUSTANDSPROTOKOLL ═══════
        if (!$this->hasTable('asset_checkinout')) {
            $this->table('asset_checkinout', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('assets_id', 'integer')
                ->addColumn('projects_id', 'integer')
                ->addColumn('assetsAssignments_id', 'integer', ['null' => true])
                ->addColumn('direction', 'string', ['limit' => 10, 'comment' => 'out (Ausgabe) or in (Ruecknahme)'])
                ->addColumn('condition_before', 'string', ['limit' => 30, 'null' => true, 'comment' => 'excellent, good, fair, poor, damaged'])
                ->addColumn('condition_after', 'string', ['limit' => 30, 'null' => true])
                ->addColumn('condition_notes', 'text', ['null' => true])
                ->addColumn('photo_s3files_id', 'integer', ['null' => true, 'comment' => 'Foto des Zustands'])
                ->addColumn('checked_by', 'integer')
                ->addColumn('checked_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('signature_data', 'text', ['null' => true, 'comment' => 'Base64 signature'])
                ->addIndex(['assets_id', 'projects_id'])
                ->addIndex(['instances_id'])
                ->create();
        }

        // ═══════ 4) REPORTS / DASHBOARDS ═══════
        // Saved report configurations
        if (!$this->hasTable('saved_reports')) {
            $this->table('saved_reports', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('report_type', 'string', ['limit' => 50, 'comment' => 'revenue, utilization, outstanding, forecast, euer'])
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('filters_json', 'text', ['null' => true, 'comment' => 'Saved filter settings'])
                ->addColumn('is_dashboard', 'boolean', ['default' => false, 'comment' => 'Show on dashboard'])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('saved_reports')->drop()->save();
        $this->table('asset_checkinout')->drop()->save();
        $this->table('recurring_invoices')->drop()->save();
        $this->table('packing_list_items')->drop()->save();
        $this->table('packing_lists')->drop()->save();
    }
}
