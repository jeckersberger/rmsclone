<?php

use Phinx\Migration\AbstractMigration;

class FlexibleLagerorte extends AbstractMigration
{
    public function change()
    {
        // Add emoji_icon field to locations table for flexible icon support
        if (!$this->table('locations')->hasColumn('emoji_icon')) {
            $this->table('locations')
                ->addColumn('emoji_icon', 'string', ['limit' => 10, 'null' => true])
                ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => true])
                ->update();
        }

        // Create bulk_move_log table for tracking bulk relocations
        if (!$this->hasTable('bulk_move_log')) {
            $bulkMoveTable = $this->table('bulk_move_log', ['signed' => false]);
            $bulkMoveTable->addColumn('entity_type', 'enum', ['values' => ['asset', 'stock_instance'], 'null' => false])
                         ->addColumn('entity_id', 'integer', ['signed' => false, 'null' => false])
                         ->addColumn('source_location_id', 'integer', ['signed' => false, 'null' => true])
                         ->addColumn('source_location_custom', 'string', ['limit' => 255, 'null' => true])
                         ->addColumn('target_location_id', 'integer', ['signed' => false, 'null' => true])
                         ->addColumn('target_location_custom', 'string', ['limit' => 255, 'null' => true])
                         ->addColumn('moved_by', 'integer', ['signed' => false, 'null' => false])
                         ->addColumn('notes', 'text', ['null' => true])
                         ->addColumn('batch_id', 'string', ['limit' => 36, 'null' => true])
                         ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                         ->addIndex(['entity_type', 'entity_id'], ['name' => 'idx_entity_bulk'])
                         ->addIndex(['target_location_id'], ['name' => 'idx_target_location_bulk'])
                         ->addIndex(['moved_by'], ['name' => 'idx_moved_by_bulk'])
                         ->addIndex(['batch_id'], ['name' => 'idx_batch_id_bulk'])
                         ->addIndex(['created_at'], ['name' => 'idx_created_at_bulk'])
                         ->create();
        }
    }
}
