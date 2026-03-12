<?php
/**
 * Logistik-Features: Lagerverwaltung, Transportplanung
 *
 * - warehouses: Lagerstandorte pro Instanz
 * - asset_warehouse_assignments: Zuordnung Equipment <-> Lager mit Mengen
 * - transport_plans: Transportplaene (Lieferungen, Abholungen)
 * - transport_items: Einzelpositionen eines Transportplans
 */
use Phinx\Migration\AbstractMigration;

class LogisticsFeatures extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) Warehouses (Lagerstandorte) ═══════
        if (!$this->hasTable('warehouses')) {
            $this->table('warehouses', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('address', 'text', ['null' => true])
                ->addColumn('contact_person', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('phone', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('is_default', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->create();
        }

        // ═══════ 2) Asset Warehouse Assignments ═══════
        if (!$this->hasTable('asset_warehouse_assignments')) {
            $this->table('asset_warehouse_assignments', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('assetTypes_id', 'integer')
                ->addColumn('warehouse_id', 'integer')
                ->addColumn('quantity', 'integer', ['default' => 0])
                ->addIndex(['assetTypes_id', 'warehouse_id'], ['unique' => true])
                ->addForeignKey('warehouse_id', 'warehouses', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->create();
        }

        // ═══════ 3) Transport Plans (Transportplaene) ═══════
        if (!$this->hasTable('transport_plans')) {
            $this->table('transport_plans', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('projects_id', 'integer', ['null' => true])
                ->addColumn('from_warehouse_id', 'integer', ['null' => true])
                ->addColumn('to_warehouse_id', 'integer', ['null' => true])
                ->addColumn('to_address', 'text', ['null' => true])
                ->addColumn('driver_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('vehicle', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('planned_date', 'date')
                ->addColumn('planned_time', 'time', ['null' => true])
                ->addColumn('status', 'enum', ['values' => ['planned', 'in_transit', 'delivered', 'cancelled'], 'default' => 'planned'])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['planned_date'])
                ->addForeignKey('from_warehouse_id', 'warehouses', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->addForeignKey('to_warehouse_id', 'warehouses', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
                ->create();
        }

        // ═══════ 4) Transport Items (Transportpositionen) ═══════
        if (!$this->hasTable('transport_items')) {
            $this->table('transport_items', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('transport_plan_id', 'integer')
                ->addColumn('assetTypes_id', 'integer')
                ->addColumn('quantity', 'integer', ['default' => 1])
                ->addColumn('loaded', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                ->addColumn('delivered', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                ->addForeignKey('transport_plan_id', 'transport_plans', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                ->addIndex(['transport_plan_id'])
                ->create();
        }
    }

    public function down()
    {
        if ($this->hasTable('transport_items')) {
            $this->table('transport_items')->drop()->save();
        }
        if ($this->hasTable('transport_plans')) {
            $this->table('transport_plans')->drop()->save();
        }
        if ($this->hasTable('asset_warehouse_assignments')) {
            $this->table('asset_warehouse_assignments')->drop()->save();
        }
        if ($this->hasTable('warehouses')) {
            $this->table('warehouses')->drop()->save();
        }
    }
}
