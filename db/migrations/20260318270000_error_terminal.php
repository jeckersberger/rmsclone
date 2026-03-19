<?php

use Phinx\Migration\AbstractMigration;

class ErrorTerminal extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('system_error_log');
        $table
            ->addColumn('level', 'enum', ['values' => ['debug','info','warning','error','critical'], 'default' => 'error', 'null' => false])
            ->addColumn('source', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('message', 'text', ['null' => false])
            ->addColumn('stack_trace', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG, 'null' => true])
            ->addColumn('context', 'json', ['null' => true])
            ->addColumn('url', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('is_resolved', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('resolved_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('resolved_at', 'timestamp', ['null' => true])
            ->addColumn('resolved_note', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['level', 'created_at'])
            ->addIndex(['instances_id', 'created_at'])
            ->addIndex(['is_resolved'])
            ->create();
    }
}
