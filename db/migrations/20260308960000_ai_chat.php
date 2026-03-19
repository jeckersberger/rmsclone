<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * KI-Chat Tabellen + Feature-Toggle + Ueberfaellige-Rueckgaben View
 */
class AiChat extends AbstractMigration
{
    public function change(): void
    {
        // ── Chat-Konversationen ──
        if (!$this->hasTable('ai_chat_conversations')) {
            $this->table('ai_chat_conversations', ['id' => true, 'signed' => false])
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('users_userid', 'integer', ['signed' => false])
                ->addColumn('title', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addColumn('archived', 'boolean', ['default' => false])
                ->addIndex(['instances_id', 'users_userid'])
                ->addIndex(['updated_at'])
                ->create();
        }

        // ── Chat-Nachrichten ──
        if (!$this->hasTable('ai_chat_messages')) {
            $this->table('ai_chat_messages', ['id' => true, 'signed' => false])
                ->addColumn('conversation_id', 'integer', ['signed' => false])
                ->addColumn('role', 'enum', ['values' => ['user', 'assistant', 'system']])
                ->addColumn('content', 'text')
                ->addColumn('tool_calls_json', 'text', ['null' => true])
                ->addColumn('tool_results_json', 'text', ['null' => true])
                ->addColumn('tokens_used', 'integer', ['default' => 0, 'signed' => false])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['conversation_id'])
                ->create();
        }

        // ── Feature-Toggle fuer Chat ──
        $instances = $this->table('instances');
        if (!$instances->hasColumn('instances_aiFeatureChat')) {
            $instances->addColumn('instances_aiFeatureChat', 'boolean', ['default' => false, 'after' => 'instances_aiFeatureDuplicateDetect'])
                ->update();
        }
    }
}
