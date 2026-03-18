<?php

use Phinx\Migration\AbstractMigration;

class AddLocations extends AbstractMigration
{
    public function change()
    {
        // Create locations table
        $locationsTable = $this->table('locations', ['signed' => false]);
        $locationsTable->addColumn('name', 'string', ['limit' => 100, 'null' => false])
                       ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
                       ->addColumn('color', 'string', ['limit' => 7, 'default' => '#6c757d'])
                       ->addColumn('icon', 'string', ['limit' => 50, 'default' => 'fas fa-warehouse'])
                       ->addColumn('is_active', 'boolean', ['default' => true])
                       ->addColumn('sort_order', 'integer', ['default' => 0])
                       ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                       ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                       ->create();

        // Insert default locations
        if ($this->isMigratingUp()) {
            $this->execute("
                INSERT INTO locations (name, description, color, icon, sort_order) VALUES
                ('Hauptlager', 'Zentrales Lager', '#007bff', 'fas fa-warehouse', 1),
                ('Fahrzeug 1', 'Einsatzfahrzeug 1', '#28a745', 'fas fa-truck', 2),
                ('Fahrzeug 2', 'Einsatzfahrzeug 2', '#28a745', 'fas fa-truck', 3),
                ('Buero', 'Bürogebäude', '#17a2b8', 'fas fa-building', 4),
                ('Werkstatt', 'Reparatur / Wartung', '#ffc107', 'fas fa-tools', 5),
                ('Extern', 'Externer Standort', '#dc3545', 'fas fa-map-marker-alt', 6)
            ");
        }

        // Create location_log table
        $logTable = $this->table('location_log', ['signed' => false]);
        $logTable->addColumn('entity_type', 'enum', ['values' => ['asset', 'stock_instance'], 'null' => false])
                 ->addColumn('entity_id', 'integer', ['unsigned' => true, 'null' => false])
                 ->addColumn('location_id', 'integer', ['unsigned' => true, 'null' => true])
                 ->addColumn('location_custom', 'string', ['limit' => 255, 'null' => true])
                 ->addColumn('previous_location_id', 'integer', ['unsigned' => true, 'null' => true])
                 ->addColumn('previous_location_custom', 'string', ['limit' => 255, 'null' => true])
                 ->addColumn('moved_by', 'integer', ['unsigned' => true, 'null' => false])
                 ->addColumn('notes', 'text', ['null' => true])
                 ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                 ->addIndex(['entity_type', 'entity_id'], ['name' => 'idx_entity'])
                 ->addIndex(['location_id'], ['name' => 'idx_location'])
                 ->addIndex(['moved_by'], ['name' => 'idx_moved_by'])
                 ->addIndex(['created_at'], ['name' => 'idx_created_at'])
                 ->create();

        // Add columns to assets table
        $assetsTable = $this->table('assets');
        $assetsTable->addColumn('current_location_id', 'integer', ['unsigned' => true, 'null' => true])
                    ->addColumn('current_location_custom', 'string', ['limit' => 255, 'null' => true])
                    ->addColumn('location_updated_at', 'timestamp', ['null' => true])
                    ->update();

        // Add columns to stock_instances table
        $stockInstancesTable = $this->table('stock_instances');
        $stockInstancesTable->addColumn('current_location_id', 'integer', ['unsigned' => true, 'null' => true])
                            ->addColumn('current_location_custom', 'string', ['limit' => 255, 'null' => true])
                            ->addColumn('location_updated_at', 'timestamp', ['null' => true])
                            ->update();
    }
}
