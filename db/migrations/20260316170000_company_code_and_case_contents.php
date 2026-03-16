<?php
use Phinx\Migration\AbstractMigration;

class CompanyCodeAndCaseContents extends AbstractMigration
{
    public function change()
    {
        // Add instances_companyCode column to instances table
        if ($this->table('instances')->hasColumn('instances_companyCode') === false) {
            $this->table('instances')
                ->addColumn('instances_companyCode', 'string', ['limit' => 10, 'null' => true, 'after' => 'instances_partnerCode'])
                ->addIndex(['instances_companyCode'], ['unique' => true])
                ->update();
        }

        // Create company_code_history table
        $companyCodeHistoryTable = $this->table('company_code_history', ['id' => 'id']);
        $companyCodeHistoryTable
            ->addColumn('instances_id', 'integer', ['null' => false])
            ->addColumn('old_code', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('new_code', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('changed_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id'])
            ->addIndex(['old_code'])
            ->create();

        // Create case_contents table (type-based matching)
        $caseContentsTable = $this->table('case_contents', ['id' => 'id']);
        $caseContentsTable
            ->addColumn('case_asset_id', 'integer', ['null' => false])
            ->addColumn('instances_id', 'integer', ['null' => false])
            ->addColumn('content_type', 'enum', ['values' => ['asset_type', 'stock_item'], 'null' => false])
            ->addColumn('content_type_id', 'integer', ['null' => false])
            ->addColumn('quantity', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('notes', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('sort_order', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['case_asset_id', 'content_type', 'content_type_id'], ['unique' => true])
            ->addIndex(['instances_id'])
            ->addIndex(['case_asset_id'])
            ->create();

        // Create case_content_checks table
        $caseContentChecksTable = $this->table('case_content_checks', ['id' => 'id']);
        $caseContentChecksTable
            ->addColumn('case_asset_id', 'integer', ['null' => false])
            ->addColumn('project_id', 'integer', ['null' => false])
            ->addColumn('instances_id', 'integer', ['null' => false])
            ->addColumn('check_type', 'enum', ['values' => ['checkout', 'checkin'], 'null' => false])
            ->addColumn('checked_by', 'integer', ['null' => false])
            ->addColumn('all_complete', 'boolean', ['null' => false, 'default' => 0])
            ->addColumn('missing_items', 'json', ['null' => true])
            ->addColumn('extra_items', 'json', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('acknowledged', 'boolean', ['null' => false, 'default' => 0])
            ->addColumn('acknowledged_by', 'integer', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['case_asset_id', 'project_id'])
            ->addIndex(['instances_id'])
            ->create();

        // Add is_case column to assets table
        if ($this->table('assets')->hasColumn('is_case') === false) {
            $this->table('assets')
                ->addColumn('is_case', 'boolean', ['null' => false, 'default' => 0])
                ->update();
        }
    }
}
