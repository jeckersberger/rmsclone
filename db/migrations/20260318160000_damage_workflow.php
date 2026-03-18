<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class DamageWorkflow extends AbstractMigration
{
    public function change(): void
    {
        // damage_workflows table
        if (!$this->hasTable('damage_workflows')) {
            $this->table('damage_workflows', [
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
            ->addColumn('damage_report_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('instances_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('status', 'enum', [
                'null' => false,
                'default' => 'reported',
                'values' => ['reported', 'assessed', 'quote_requested', 'quote_received',
                            'repair_approved', 'in_repair', 'repaired', 'verified',
                            'charged', 'insurance_claimed', 'closed'],
            ])
            ->addColumn('severity', 'enum', [
                'null' => false,
                'default' => 'minor',
                'values' => ['minor', 'moderate', 'major', 'total_loss'],
            ])
            ->addColumn('estimated_cost', 'decimal', [
                'null' => true,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('actual_cost', 'decimal', [
                'null' => true,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('repair_vendor', 'string', [
                'null' => true,
                'limit' => 255,
            ])
            ->addColumn('repair_vendor_contact', 'string', [
                'null' => true,
                'limit' => 255,
            ])
            ->addColumn('insurance_claim_id', 'string', [
                'null' => true,
                'limit' => 255,
            ])
            ->addColumn('insurance_claim_status', 'enum', [
                'null' => true,
                'values' => ['not_claimed', 'claimed', 'approved', 'rejected', 'paid'],
            ])
            ->addColumn('customer_charged', 'boolean', [
                'null' => false,
                'default' => false,
            ])
            ->addColumn('customer_charge_amount', 'decimal', [
                'null' => true,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('customer_charge_invoice_id', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('deposit_deducted', 'boolean', [
                'null' => false,
                'default' => false,
            ])
            ->addColumn('deposit_deduction_amount', 'decimal', [
                'null' => true,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('assigned_to', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('notes', 'text', [
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
            ->addIndex(['damage_report_id'])
            ->addIndex(['instances_id'])
            ->addIndex(['status'])
            ->addIndex(['severity'])
            ->addIndex(['assigned_to'])
            ->save();
        }

        // damage_workflow_log table
        if (!$this->hasTable('damage_workflow_log')) {
            $this->table('damage_workflow_log', [
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
            ->addColumn('workflow_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('from_status', 'string', [
                'null' => true,
                'limit' => 50,
            ])
            ->addColumn('to_status', 'string', [
                'null' => false,
                'limit' => 50,
            ])
            ->addColumn('changed_by', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('notes', 'text', [
                'null' => true,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['workflow_id'])
            ->addIndex(['created_at'])
            ->save();
        }

        // damage_cost_estimates table
        if (!$this->hasTable('damage_cost_estimates')) {
            $this->table('damage_cost_estimates', [
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
            ->addColumn('workflow_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('vendor_name', 'string', [
                'null' => false,
                'limit' => 255,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
            ])
            ->addColumn('amount', 'decimal', [
                'null' => false,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('is_accepted', 'boolean', [
                'null' => false,
                'default' => false,
            ])
            ->addColumn('document_path', 'string', [
                'null' => true,
                'limit' => 500,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['workflow_id'])
            ->addIndex(['is_accepted'])
            ->save();
        }

        // damage_photos table
        if (!$this->hasTable('damage_photos')) {
            $this->table('damage_photos', [
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
            ->addColumn('damage_report_id', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('file_path', 'string', [
                'null' => false,
                'limit' => 500,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
            ])
            ->addColumn('photo_type', 'enum', [
                'null' => false,
                'default' => 'initial',
                'values' => ['initial', 'during_repair', 'after_repair'],
            ])
            ->addColumn('uploaded_by', 'integer', [
                'null' => false,
                'limit' => MysqlAdapter::INT_REGULAR,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['damage_report_id'])
            ->addIndex(['photo_type'])
            ->save();
        }
    }
}
