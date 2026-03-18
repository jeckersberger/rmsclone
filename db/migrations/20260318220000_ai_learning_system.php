<?php

use Phinx\Migration\AbstractMigration;

class AiLearningSystem extends AbstractMigration
{
    public function change(): void
    {
        // AI Feedback table - records user feedback on AI outputs
        if (!$this->hasTable('ai_feedback')) {
            $this->table('ai_feedback')
                ->addColumn('user_id', 'integer')
                ->addColumn('instance_id', 'integer')
                ->addColumn('task_type', 'string', ['limit' => 50])
                ->addColumn('ai_output', 'text')
                ->addColumn('user_edited', 'text', ['null' => true])
                ->addColumn('rating', 'enum', ['values' => ['positive', 'negative', 'neutral'], 'default' => 'neutral'])
                ->addColumn('feedback_reason', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('feedback_text', 'text', ['null' => true])
                ->addColumn('accepted', 'boolean', ['default' => false])
                ->addColumn('edit_time_ms', 'integer', ['null' => true])
                ->addColumn('provider', 'string', ['limit' => 50])
                ->addColumn('model', 'string', ['limit' => 100])
                ->addColumn('prompt_version', 'integer', ['default' => 1])
                ->addColumn('tokens_input', 'integer')
                ->addColumn('tokens_output', 'integer')
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['user_id'])
                ->addIndex(['instance_id'])
                ->addIndex(['task_type'])
                ->addIndex(['rating'])
                ->addIndex(['provider'])
                ->addIndex(['created_at'])
                ->create();
        }

        // AI Few-Shot Examples - high-quality input/output examples for in-context learning
        if (!$this->hasTable('ai_few_shot_examples')) {
            $this->table('ai_few_shot_examples')
                ->addColumn('instance_id', 'integer')
                ->addColumn('task_type', 'string', ['limit' => 50])
                ->addColumn('input_context', 'text')
                ->addColumn('output_example', 'text')
                ->addColumn('positive_votes', 'integer', ['default' => 0])
                ->addColumn('negative_votes', 'integer', ['default' => 0])
                ->addColumn('usage_count', 'integer', ['default' => 0])
                ->addColumn('is_active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instance_id'])
                ->addIndex(['task_type'])
                ->addIndex(['is_active'])
                ->create();
        }

        // AI Prompt Versions - version control for system prompts with A/B testing
        if (!$this->hasTable('ai_prompt_versions')) {
            $this->table('ai_prompt_versions')
                ->addColumn('task_type', 'string', ['limit' => 50])
                ->addColumn('version', 'integer')
                ->addColumn('system_prompt', 'text')
                ->addColumn('change_reason', 'text', ['null' => true])
                ->addColumn('change_source', 'enum', ['values' => ['manual', 'automatic', 'ab_test']])
                ->addColumn('acceptance_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
                ->addColumn('total_uses', 'integer', ['default' => 0])
                ->addColumn('is_active', 'boolean', ['default' => false])
                ->addColumn('is_ab_test', 'boolean', ['default' => false])
                ->addColumn('ab_test_traffic_pct', 'integer', ['default' => 50])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['task_type'])
                ->addIndex(['version'])
                ->addIndex(['is_active'])
                ->addIndex(['is_ab_test'])
                ->create();
        }

        // AI Learning Profile - learnings about user/instance preferences
        if (!$this->hasTable('ai_learning_profile')) {
            $this->table('ai_learning_profile')
                ->addColumn('instance_id', 'integer')
                ->addColumn('profile_key', 'string', ['limit' => 100])
                ->addColumn('profile_value', 'text')
                ->addColumn('confidence', 'decimal', ['precision' => 3, 'scale' => 2])
                ->addColumn('data_points', 'integer', ['default' => 0])
                ->addColumn('last_updated', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instance_id'])
                ->addIndex(['profile_key'])
                ->addIndex(['last_updated'])
                ->addUniqueIndex(['instance_id', 'profile_key'])
                ->create();
        }
    }
}
