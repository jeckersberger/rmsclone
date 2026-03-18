<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AiTaskTracking extends AbstractMigration
{
    public function change(): void
    {
        // Table: ai_feature_requests
        // Tracks feature request lifecycle: idea -> formulation -> approval -> implementation -> done
        $featureRequests = $this->table('ai_feature_requests', ['signed' => false]);
        $featureRequests
            ->addColumn('fr_number', 'string', ['limit' => 10, 'comment' => 'FR-001, FR-002, etc.'])
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('status', 'enum', [
                'values' => ['idea', 'formulated', 'approved', 'in_progress', 'done'],
                'default' => 'idea',
                'comment' => 'idea=user submitted, formulated=KI expanded, approved=user accepted, in_progress=being built, done=completed'
            ])
            ->addColumn('user_idea', 'text', ['comment' => 'Original user input (unmodified)'])
            ->addColumn('ai_formulation', 'text', ['null' => true, 'comment' => 'KI-generated detailed formulation (what/why/how/db/api/ui)'])
            ->addColumn('priority', 'enum', [
                'values' => ['high', 'medium', 'low'],
                'null' => true,
            ])
            ->addColumn('estimated_size', 'enum', [
                'values' => ['s', 'm', 'l', 'xl'],
                'null' => true,
                'comment' => 'Size estimate for planning: small, medium, large, extra-large'
            ])
            ->addColumn('commit_hash', 'string', ['limit' => 40, 'null' => true, 'comment' => 'Git commit SHA when feature was completed'])
            ->addColumn('instances_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['fr_number', 'instances_id'], ['unique' => true, 'name' => 'idx_fr_number_instance'])
            ->addIndex(['instances_id', 'status'])
            ->addIndex(['created_at'])
            ->create();

        // Table: ai_implementation_status
        // Tracks implementation progress of features by module and baustein (component)
        $implementationStatus = $this->table('ai_implementation_status', ['signed' => false]);
        $implementationStatus
            ->addColumn('module_code', 'string', ['limit' => 10, 'comment' => 'Module identifier: L1, L2, J1, J2, K1, K2, K3, I1-I10'])
            ->addColumn('module_name', 'string', ['limit' => 100, 'comment' => 'Human-readable module name'])
            ->addColumn('baustein_nr', 'integer', ['signed' => false, 'comment' => 'Sequential number within module (1, 2, 3, ...)'])
            ->addColumn('baustein_name', 'string', ['limit' => 255, 'comment' => 'Description of this specific task/component'])
            ->addColumn('status', 'enum', [
                'values' => ['done', 'review', 'open', 'needs_tests'],
                'default' => 'open',
                'comment' => 'done=implemented & committed, review=code ready for review, open=not started, needs_tests=code needs unit tests'
            ])
            ->addColumn('file_path', 'string', ['limit' => 500, 'null' => true, 'comment' => 'Primary file implementing this baustein'])
            ->addColumn('instances_id', 'integer', ['signed' => false])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['module_code', 'baustein_nr', 'instances_id'], ['unique' => true, 'name' => 'idx_module_baustein_instance'])
            ->addIndex(['instances_id', 'status'])
            ->create();

        // Table: ai_task_log
        // Audit trail of what the KI system did automatically (feature request processing, status updates, etc.)
        $taskLog = $this->table('ai_task_log', ['signed' => false]);
        $taskLog
            ->addColumn('task_type', 'string', ['limit' => 50, 'comment' => 'Type of action: formulate_request, update_status, sync_markdown, update_baustein, etc.'])
            ->addColumn('module_code', 'string', ['limit' => 10, 'null' => true, 'comment' => 'Related module code if applicable'])
            ->addColumn('action', 'string', ['limit' => 100, 'comment' => 'Specific action description (e.g. "formulated FR-005", "updated L1 baustein 12 to done")'])
            ->addColumn('details', 'text', ['null' => true, 'comment' => 'Additional context (FR number, file changes, errors, etc.)'])
            ->addColumn('user_id', 'integer', ['signed' => false, 'comment' => 'User ID who triggered this (0 if automated)'])
            ->addColumn('instances_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['instances_id', 'created_at'])
            ->addIndex(['task_type'])
            ->create();
    }
}
