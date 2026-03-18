<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class InsuranceManagement extends AbstractMigration
{
    public function change(): void
    {
        // insurance_policies table
        if (!$this->hasTable('insurance_policies')) {
            $this->table('insurance_policies', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
            ])
            ->addColumn('instances_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('name', 'string', [
                'null' => false,
                'limit' => 255,
            ])
            ->addColumn('provider', 'string', [
                'null' => false,
                'limit' => 255,
            ])
            ->addColumn('policy_number', 'string', [
                'null' => false,
                'limit' => 100,
            ])
            ->addColumn('coverage_type', 'enum', [
                'null' => false,
                'values' => ['all_risk', 'transport', 'liability', 'equipment', 'event'],
            ])
            ->addColumn('coverage_amount', 'decimal', [
                'null' => false,
                'precision' => 12,
                'scale' => 2,
            ])
            ->addColumn('deductible', 'decimal', [
                'null' => true,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('premium_monthly', 'decimal', [
                'null' => false,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('valid_from', 'date', [
                'null' => false,
            ])
            ->addColumn('valid_until', 'date', [
                'null' => false,
            ])
            ->addColumn('document_path', 'string', [
                'null' => true,
                'limit' => 500,
            ])
            ->addColumn('notes', 'text', [
                'null' => true,
            ])
            ->addColumn('is_active', 'boolean', [
                'null' => false,
                'default' => true,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['instances_id'])
            ->addIndex(['policy_number'])
            ->addIndex(['coverage_type'])
            ->addIndex(['is_active'])
            ->addIndex(['valid_until'])
            ->save();
        }

        // insurance_asset_coverage table - Maps assets to policies
        if (!$this->hasTable('insurance_asset_coverage')) {
            $this->table('insurance_asset_coverage', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
            ])
            ->addColumn('policy_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('asset_id', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('asset_type_id', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('stock_item_id', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['policy_id'])
            ->addIndex(['asset_id'])
            ->addIndex(['asset_type_id'])
            ->addIndex(['stock_item_id'])
            ->save();
        }

        // insurance_client_certificates table - Client liability/event certificates
        if (!$this->hasTable('insurance_client_certificates')) {
            $this->table('insurance_client_certificates', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
            ])
            ->addColumn('instances_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('client_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('certificate_type', 'enum', [
                'null' => false,
                'values' => ['liability', 'property', 'event'],
            ])
            ->addColumn('file_path', 'string', [
                'null' => false,
                'limit' => 500,
            ])
            ->addColumn('valid_until', 'date', [
                'null' => false,
            ])
            ->addColumn('verified', 'boolean', [
                'null' => false,
                'default' => false,
            ])
            ->addColumn('verified_by', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('verified_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['instances_id'])
            ->addIndex(['client_id'])
            ->addIndex(['certificate_type'])
            ->addIndex(['valid_until'])
            ->save();
        }

        // insurance_claims table - Track insurance claims
        if (!$this->hasTable('insurance_claims')) {
            $this->table('insurance_claims', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
                'identity' => 'enable',
            ])
            ->addColumn('instances_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('policy_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('damage_workflow_id', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('claim_number', 'string', [
                'null' => false,
                'limit' => 100,
            ])
            ->addColumn('claim_date', 'date', [
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
            ])
            ->addColumn('claimed_amount', 'decimal', [
                'null' => false,
                'precision' => 12,
                'scale' => 2,
            ])
            ->addColumn('approved_amount', 'decimal', [
                'null' => true,
                'precision' => 12,
                'scale' => 2,
            ])
            ->addColumn('status', 'enum', [
                'null' => false,
                'default' => 'draft',
                'values' => ['draft', 'submitted', 'under_review', 'approved', 'partially_approved', 'rejected', 'paid', 'closed'],
            ])
            ->addColumn('payout_date', 'date', [
                'null' => true,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['instances_id'])
            ->addIndex(['policy_id'])
            ->addIndex(['claim_number'])
            ->addIndex(['status'])
            ->addIndex(['damage_workflow_id'])
            ->save();
        }
    }
}
