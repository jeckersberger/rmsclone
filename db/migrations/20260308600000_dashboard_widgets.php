<?php
use Phinx\Migration\AbstractMigration;

class DashboardWidgets extends AbstractMigration
{
    public function up()
    {
        if (!$this->hasTable('dashboard_widget_config')) {
            $this->table('dashboard_widget_config', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('users_userid', 'string', ['limit' => 255])
                ->addColumn('instances_id', 'integer')
                ->addColumn('widget_key', 'string', ['limit' => 100])
                ->addColumn('position', 'integer', ['default' => 0])
                ->addColumn('visible', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1])
                ->addColumn('size', 'enum', ['values' => ['small', 'medium', 'large'], 'default' => 'medium'])
                ->addColumn('config_json', 'text', ['null' => true])
                ->addIndex(['users_userid', 'instances_id', 'widget_key'], ['unique' => true])
                ->create();
        }
    }

    public function down()
    {
        $this->table('dashboard_widget_config')->drop()->save();
    }
}
