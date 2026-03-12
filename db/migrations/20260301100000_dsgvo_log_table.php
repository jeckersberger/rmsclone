<?php
/**
 * DSGVO Log Table Migration
 * Stores audit log of all DSGVO-related actions (data exports, anonymizations, etc.)
 */
use Phinx\Migration\AbstractMigration;

class DsgvoLogTable extends AbstractMigration
{
    public function up()
    {
        if (!$this->hasTable('dsgvo_log')) {
            $this->table('dsgvo_log', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('clients_id', 'integer')
                ->addColumn('action', 'string', ['limit' => 50, 'comment' => 'data_export, deletion_request, partial_anonymization, full_anonymization'])
                ->addColumn('performed_by', 'integer', ['comment' => 'users_userid'])
                ->addColumn('details', 'text', ['null' => true])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['clients_id'])
                ->addIndex(['action'])
                ->addIndex(['created_at'])
                ->create();
        }
    }

    public function down()
    {
        $this->table('dsgvo_log')->drop()->save();
    }
}
