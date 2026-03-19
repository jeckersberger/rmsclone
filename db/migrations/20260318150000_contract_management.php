<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class ContractManagement extends AbstractMigration
{
    public function change(): void
    {
        // contracts table
        if (!$this->hasTable('contracts')) {
            $this->table('contracts', ['collation' => 'utf8mb4_unicode_ci'])
                ->addColumn('instances_id', 'integer', ['null' => false])
                ->addColumn('projects_id', 'integer', ['null' => true])
                ->addColumn('clients_id', 'integer', ['null' => false])
                ->addColumn('template_id', 'integer', ['null' => true])
                ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('content_html', 'longtext', ['null' => false])
                ->addColumn('status', 'enum', ['values' => ['draft', 'sent', 'viewed', 'signed', 'active', 'expired', 'cancelled'], 'default' => 'draft'])
                ->addColumn('version', 'integer', ['default' => 1])
                ->addColumn('valid_from', 'date', ['null' => true])
                ->addColumn('valid_until', 'date', ['null' => true])
                ->addColumn('signed_at', 'datetime', ['null' => true])
                ->addColumn('signed_by_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('signed_by_ip', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('signature_data', 'longtext', ['null' => true])
                ->addColumn('signature_method', 'enum', ['values' => ['canvas', 'docusign', 'adobe_sign'], 'null' => true])
                ->addColumn('created_by', 'integer', ['null' => false])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['projects_id'])
                ->addIndex(['clients_id'])
                ->addIndex(['status'])
                ->addIndex(['created_at'])
                ->create();
        }

        // contract_versions table
        if (!$this->hasTable('contract_versions')) {
            $this->table('contract_versions', ['collation' => 'utf8mb4_unicode_ci'])
                ->addColumn('contracts_id', 'integer', ['null' => false])
                ->addColumn('version', 'integer', ['null' => false])
                ->addColumn('content_html', 'longtext', ['null' => false])
                ->addColumn('changed_by', 'integer', ['null' => false])
                ->addColumn('changed_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('change_notes', 'text', ['null' => true])
                ->addIndex(['contracts_id'])
                ->addIndex(['version'])
                ->create();
        }

        // contract_templates table
        if (!$this->hasTable('contract_templates')) {
            $this->table('contract_templates', ['collation' => 'utf8mb4_unicode_ci'])
                ->addColumn('instances_id', 'integer', ['null' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('category', 'enum', ['values' => ['rental', 'service', 'nda', 'general'], 'default' => 'general'])
                ->addColumn('content_html', 'longtext', ['null' => false])
                ->addColumn('placeholders', 'json', ['null' => true])
                ->addColumn('agb_set_id', 'integer', ['null' => true])
                ->addColumn('is_active', 'boolean', ['default' => 1])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['category'])
                ->addIndex(['is_active'])
                ->create();
        }

        // contract_agb_sets table
        if (!$this->hasTable('contract_agb_sets')) {
            $this->table('contract_agb_sets', ['collation' => 'utf8mb4_unicode_ci'])
                ->addColumn('instances_id', 'integer', ['null' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('content_html', 'longtext', ['null' => false])
                ->addColumn('version', 'integer', ['default' => 1])
                ->addColumn('is_active', 'boolean', ['default' => 1])
                ->addColumn('is_default', 'boolean', ['default' => 0])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['is_active'])
                ->addIndex(['is_default'])
                ->create();
        }

        // contract_audit_log table
        if (!$this->hasTable('contract_audit_log')) {
            $this->table('contract_audit_log', ['collation' => 'utf8mb4_unicode_ci'])
                ->addColumn('contracts_id', 'integer', ['null' => false])
                ->addColumn('action', 'enum', ['values' => ['created', 'edited', 'sent', 'viewed', 'signed', 'cancelled', 'expired']])
                ->addColumn('users_id', 'integer', ['null' => true])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('user_agent', 'text', ['null' => true])
                ->addColumn('details', 'json', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['contracts_id'])
                ->addIndex(['action'])
                ->addIndex(['created_at'])
                ->create();
        }
    }
}
