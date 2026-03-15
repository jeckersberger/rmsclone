<?php

use Phinx\Migration\AbstractMigration;

/**
 * Stock Items & Instances System
 *
 * Introduces two-tier tracking:
 * - Assets (Geräte): High-value items with serial numbers, full profiles
 * - Stock Items (Artikel): Bulk/consumable items tracked via individual instances
 *
 * Each physical item (asset or stock instance) gets a unique RFID EPC,
 * enabling box-scan aggregation without duplicate errors.
 */
class StockItemsAndInstances extends AbstractMigration
{
    public function change(): void
    {
        // ── Stock Item Types (Artikeltypen) ──
        // e.g. "HDMI-Kabel 3m", "XLR-Kabel 5m", "Kaltgerätekabel"
        if (!$this->hasTable('stock_items')) {
            $this->table('stock_items')
                ->addColumn('instances_id', 'integer')
                ->addColumn('name', 'string', ['limit' => 200])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('category', 'string', ['limit' => 100, 'default' => ''])
                ->addColumn('sku', 'string', ['limit' => 50, 'default' => ''])
                ->addColumn('unit_value', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('day_rate', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('week_rate', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
                ->addColumn('min_stock', 'integer', ['default' => 0])
                ->addColumn('image_url', 'string', ['limit' => 500, 'null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addColumn('created_at', 'datetime')
                ->addColumn('updated_at', 'datetime')
                ->addIndex(['instances_id', 'name'])
                ->addIndex(['instances_id', 'sku'])
                ->addIndex(['instances_id', 'category'])
                ->addIndex(['active'])
                ->create();
        }

        // ── Stock Instances (einzelne physische Exemplare) ──
        // Each row = one physical cable/adapter/etc with its own RFID tag
        if (!$this->hasTable('stock_instances')) {
            $this->table('stock_instances')
                ->addColumn('stock_item_id', 'integer')
                ->addColumn('instances_id', 'integer')
                ->addColumn('instance_number', 'integer')  // Running number within item type
                ->addColumn('rfid_tag', 'string', ['limit' => 48, 'null' => true])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'available'])
                    // available, checked_out, damaged, lost, retired
                ->addColumn('condition', 'string', ['limit' => 20, 'default' => 'good'])
                    // good, fair, poor
                ->addColumn('location', 'string', ['limit' => 200, 'default' => ''])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('last_scan_at', 'datetime', ['null' => true])
                ->addColumn('created_at', 'datetime')
                ->addColumn('updated_at', 'datetime')
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addIndex(['rfid_tag'], ['unique' => true, 'name' => 'idx_stock_instances_rfid'])
                ->addIndex(['stock_item_id'])
                ->addIndex(['instances_id', 'status'])
                ->addIndex(['stock_item_id', 'instance_number'], ['unique' => true])
                ->create();
        }

        // ── Stock Assignments (Ausleihe von Artikel-Instanzen) ──
        if (!$this->hasTable('stock_assignments')) {
            $this->table('stock_assignments')
                ->addColumn('stock_instance_id', 'integer')
                ->addColumn('instances_id', 'integer')
                ->addColumn('projects_id', 'integer')
                ->addColumn('assigned_by', 'integer')
                ->addColumn('assignment_start', 'datetime')
                ->addColumn('assignment_end', 'datetime', ['null' => true])
                ->addColumn('notes', 'string', ['limit' => 255, 'default' => ''])
                ->addIndex(['stock_instance_id'])
                ->addIndex(['projects_id'])
                ->addIndex(['instances_id'])
                ->addIndex(['assignment_end'])
                ->create();
        }

        // ── Box Scan Sessions (Kisten-Scan) ──
        if (!$this->hasTable('box_scan_sessions')) {
            $this->table('box_scan_sessions')
                ->addColumn('instances_id', 'integer')
                ->addColumn('users_userid', 'integer')
                ->addColumn('name', 'string', ['limit' => 100, 'default' => ''])
                ->addColumn('projects_id', 'integer', ['null' => true])
                ->addColumn('scan_mode', 'string', ['limit' => 20, 'default' => 'count'])
                    // count, checkout, checkin
                ->addColumn('total_scanned', 'integer', ['default' => 0])
                ->addColumn('total_assets', 'integer', ['default' => 0])
                ->addColumn('total_stock', 'integer', ['default' => 0])
                ->addColumn('total_unknown', 'integer', ['default' => 0])
                ->addColumn('scan_data', 'text', ['limit' => 16777215]) // MEDIUMTEXT JSON
                ->addColumn('session_started', 'datetime')
                ->addColumn('session_ended', 'datetime', ['null' => true])
                ->addIndex(['instances_id'])
                ->addIndex(['session_started'])
                ->create();
        }

        // ── Add entity_type to rfid-related tables for dual tracking ──
        // Extend rfid_tags to support stock instances
        if ($this->hasTable('rfid_tags')) {
            $rfidTags = $this->table('rfid_tags');
            if (!$rfidTags->hasColumn('entity_type')) {
                $rfidTags
                    ->addColumn('entity_type', 'string', ['limit' => 20, 'default' => 'asset', 'after' => 'asset_id'])
                    // 'asset' or 'stock_instance'
                    ->addColumn('stock_instance_id', 'integer', ['null' => true, 'after' => 'entity_type'])
                    ->addIndex(['entity_type'])
                    ->save();
            }
        }

        // Add rfid_tag column to rfidInventoryScans for stock instances
        if ($this->hasTable('rfidInventoryScans')) {
            $invScans = $this->table('rfidInventoryScans');
            if (!$invScans->hasColumn('entity_type')) {
                $invScans
                    ->addColumn('entity_type', 'string', ['limit' => 20, 'default' => 'asset'])
                    ->addColumn('stock_instance_id', 'integer', ['null' => true])
                    ->save();
            }
        }
    }
}
