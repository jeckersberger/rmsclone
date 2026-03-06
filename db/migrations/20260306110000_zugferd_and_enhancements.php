<?php
/**
 * Adds ZUGFeRD XML support field to document_exports
 * and adds cron_last_run tracking table.
 */
use Phinx\Migration\AbstractMigration;

class ZugferdAndEnhancements extends AbstractMigration
{
    public function change()
    {
        // Add ZUGFeRD XML file reference to document_exports
        $docExports = $this->table('document_exports');
        if (!$docExports->hasColumn('zugferd_xml_s3files_id')) {
            $docExports->addColumn('zugferd_xml_s3files_id', 'integer', [
                'null' => true,
                'after' => 's3files_id',
                'comment' => 'S3 file ID of ZUGFeRD/Factur-X XML',
            ]);
            $docExports->update();
        }

        // Cron job tracking table
        if (!$this->hasTable('cron_runs')) {
            $this->table('cron_runs', ['id' => true, 'primary_key' => ['id']])
                ->addColumn('instances_id', 'integer')
                ->addColumn('job_type', 'string', ['limit' => 50, 'comment' => 'recurring_projects, overdue_check, dunning_auto'])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'success'])
                ->addColumn('items_processed', 'integer', ['default' => 0])
                ->addColumn('details_json', 'text', ['null' => true])
                ->addColumn('started_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('completed_at', 'datetime', ['null' => true])
                ->addIndex(['instances_id', 'job_type'])
                ->addIndex(['started_at'])
                ->create();
        }
    }
}
