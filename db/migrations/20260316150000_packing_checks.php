<?php
use Phinx\Migration\AbstractMigration;

class PackingChecks extends AbstractMigration
{
    public function change()
    {
        if (!$this->hasTable('packing_checks')) {
            $table = $this->table('packing_checks', ['signed' => false]);
            $table
                ->addColumn('entity_type', 'enum', ['values' => ['asset', 'stock_instance', 'external']])
                ->addColumn('entity_id', 'integer', ['signed' => false])
                ->addColumn('project_id', 'integer', ['signed' => false])
                ->addColumn('checked_by', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('checked_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['entity_type', 'entity_id', 'project_id'], ['unique' => true])
                ->addIndex(['project_id'])
                ->create();
        }
    }
}
