<?php
use Phinx\Migration\AbstractMigration;

class TransportLogistics extends AbstractMigration
{
    public function change()
    {
        // Transport vehicles (Transporter, Lastwagen, Anhänger)
        $table = $this->table('transport_vehicles', ['signed' => false]);
        $table
            ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('type', 'enum', ['values' => ['van', 'truck', 'trailer', 'car'], 'null' => false])
            ->addColumn('license_plate', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('max_weight_kg', 'integer', ['null' => true, 'comment' => 'Maximum weight capacity in kg'])
            ->addColumn('cargo_volume_m3', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true, 'comment' => 'Cargo volume in cubic meters'])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id'])
            ->addIndex(['is_active'])
            ->create();

        // Transport drivers
        $table = $this->table('transport_drivers', ['signed' => false]);
        $table
            ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('users_userid', 'integer', ['null' => false, 'comment' => 'FK to users table'])
            ->addColumn('license_types', 'string', ['limit' => 100, 'null' => true, 'comment' => 'e.g., B,C,CE'])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('is_available', 'boolean', ['default' => true])
            ->addIndex(['instances_id'])
            ->addIndex(['users_userid'])
            ->create();

        // Transport tours (Transportfahrten/Touren)
        $table = $this->table('transport_tours', ['signed' => false]);
        $table
            ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 200, 'null' => false])
            ->addColumn('date', 'date', ['null' => false])
            ->addColumn('driver_id', 'integer', ['null' => true, 'comment' => 'FK to transport_drivers'])
            ->addColumn('vehicle_id', 'integer', ['null' => true, 'comment' => 'FK to transport_vehicles'])
            ->addColumn('status', 'enum', ['values' => ['planned', 'loading', 'in_transit', 'delivering', 'completed', 'cancelled'], 'default' => 'planned'])
            ->addColumn('total_distance_km', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('total_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id'])
            ->addIndex(['date'])
            ->addIndex(['status'])
            ->addIndex(['driver_id'])
            ->addIndex(['vehicle_id'])
            ->create();

        // Tour stops (Haltestellen: Abhol- und Lieferstationen)
        $table = $this->table('transport_tour_stops', ['signed' => false]);
        $table
            ->addColumn('tour_id', 'integer', ['null' => false, 'comment' => 'FK to transport_tours'])
            ->addColumn('stop_order', 'integer', ['null' => false, 'comment' => 'Order in tour'])
            ->addColumn('type', 'enum', ['values' => ['pickup', 'delivery', 'return'], 'null' => false])
            ->addColumn('project_id', 'integer', ['null' => true, 'comment' => 'FK to projects (optional)'])
            ->addColumn('client_id', 'integer', ['null' => true, 'comment' => 'FK to clients (optional)'])
            ->addColumn('address', 'text', ['null' => true])
            ->addColumn('time_window_start', 'time', ['null' => true, 'comment' => 'Earliest arrival time'])
            ->addColumn('time_window_end', 'time', ['null' => true, 'comment' => 'Latest arrival time'])
            ->addColumn('arrived_at', 'datetime', ['null' => true])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('confirmed_by_signature', 'boolean', ['default' => false])
            ->addColumn('signature_data', 'text', ['null' => true, 'comment' => 'Base64 encoded signature or JSON'])
            ->addColumn('photo_path', 'string', ['limit' => 255, 'null' => true, 'comment' => 'S3 path or local path to photo'])
            ->addColumn('notes', 'text', ['null' => true])
            ->addIndex(['tour_id'])
            ->addIndex(['project_id'])
            ->addIndex(['client_id'])
            ->create();

        // Transport costs (Kostenbelege)
        $table = $this->table('transport_costs', ['signed' => false]);
        $table
            ->addColumn('tour_id', 'integer', ['null' => false, 'comment' => 'FK to transport_tours'])
            ->addColumn('cost_type', 'enum', ['values' => ['fuel', 'toll', 'parking', 'other'], 'null' => false])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('receipt_path', 'string', ['limit' => 255, 'null' => true, 'comment' => 'S3 or local path'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['tour_id'])
            ->addIndex(['cost_type'])
            ->create();

        // Optional: Add foreign key constraints if needed (Phinx supports this)
        // Note: For safety with existing legacy systems, we don't enforce strict FKs by default
    }
}
