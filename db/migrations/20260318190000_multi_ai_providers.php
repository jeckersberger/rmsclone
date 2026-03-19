<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * Multi-KI-Provider System Migration
 *
 * Implements a provider-agnostic abstraction layer supporting:
 * - OpenAI, Claude, Gemini, Mistral, Ollama, Custom OpenAI-Compatible APIs
 * - Task-based routing and fallback chains
 * - Usage tracking and cost estimation
 */
final class MultiAiProviders extends AbstractMigration
{
    public function change(): void
    {
        // ── Table 1: AI Providers ──
        // Defines available LLM providers and their configuration
        if (!$this->hasTable('ai_providers')) {
            $table = $this->table('ai_providers', ['id' => false, 'primary_key' => ['id']]);
            $table->addColumn('id', 'integer', ['autoIncrement' => true, 'signed' => false])
                ->addColumn('instances_id', 'integer', ['signed' => false, 'comment' => 'Multi-tenancy'])
                ->addColumn('name', 'string', [
                    'limit' => 128,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'User-friendly name (e.g., "Claude Main", "OpenAI GPT-4")',
                ])
                ->addColumn('provider_type', 'enum', [
                    'values' => ['openai', 'claude', 'gemini', 'mistral', 'ollama', 'openai_compatible'],
                    'comment' => 'Provider type determines which adapter is used',
                ])
                ->addColumn('api_key_encrypted', 'text', [
                    'null' => true,
                    'limit' => MysqlAdapter::TEXT_MEDIUM,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Encrypted with AES-256-GCM, null for Ollama (no auth)',
                ])
                ->addColumn('base_url', 'string', [
                    'null' => true,
                    'limit' => 255,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Custom base URL for OpenAI-compatible, Ollama, or overrides',
                ])
                ->addColumn('default_model', 'string', [
                    'limit' => 128,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Default model for this provider (e.g., claude-haiku-4-5-20251001)',
                ])
                ->addColumn('is_active', 'boolean', [
                    'default' => 1,
                    'limit' => MysqlAdapter::INT_TINY,
                    'comment' => 'Whether this provider is available for use',
                ])
                ->addColumn('is_default', 'boolean', [
                    'default' => 0,
                    'limit' => MysqlAdapter::INT_TINY,
                    'comment' => 'Default provider for this instance',
                ])
                ->addColumn('config', 'json', [
                    'null' => true,
                    'comment' => 'Provider-specific config (temperature, request_timeout_seconds, max_batch_size, etc.)',
                ])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', [
                    'default' => 'CURRENT_TIMESTAMP',
                    'update' => 'CURRENT_TIMESTAMP',
                ])
                ->addIndex(['instances_id'], ['name' => 'idx_ai_providers_instance'])
                ->addIndex(['provider_type'], ['name' => 'idx_ai_providers_type'])
                ->addIndex(['is_active'], ['name' => 'idx_ai_providers_active'])
                ->create();
        }

        // ── Table 2: AI Task Routing ──
        // Maps task types to preferred providers and models
        if (!$this->hasTable('ai_task_routing')) {
            $table = $this->table('ai_task_routing', ['id' => false, 'primary_key' => ['id']]);
            $table->addColumn('id', 'integer', ['autoIncrement' => true, 'signed' => false])
                ->addColumn('instances_id', 'integer', ['signed' => false, 'comment' => 'Multi-tenancy'])
                ->addColumn('task_type', 'string', [
                    'limit' => 64,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Task identifier (e.g., asset_lookup, invoice_scan, chat)',
                ])
                ->addColumn('provider_id', 'integer', [
                    'signed' => false,
                    'comment' => 'Preferred provider for this task type',
                ])
                ->addColumn('model_override', 'string', [
                    'null' => true,
                    'limit' => 128,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Optional override of provider\'s default model for this task',
                ])
                ->addColumn('priority', 'integer', [
                    'default' => 0,
                    'comment' => 'Priority order for fallback chain',
                ])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', [
                    'default' => 'CURRENT_TIMESTAMP',
                    'update' => 'CURRENT_TIMESTAMP',
                ])
                ->addIndex(['instances_id'], ['name' => 'idx_ai_task_routing_instance'])
                ->addIndex(['task_type'], ['name' => 'idx_ai_task_routing_task'])
                ->addIndex(['provider_id'], ['name' => 'idx_ai_task_routing_provider'])
                ->create();
        }

        // ── Table 3: AI Usage Log ──
        // Track all AI API calls for billing and analytics
        if (!$this->hasTable('ai_usage_log')) {
            $table = $this->table('ai_usage_log', ['id' => false, 'primary_key' => ['id']]);
            $table->addColumn('id', 'biginteger', ['autoIncrement' => true, 'signed' => false])
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('provider_id', 'integer', [
                    'signed' => false,
                    'comment' => 'Which provider was used',
                ])
                ->addColumn('model', 'string', [
                    'limit' => 128,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Model name (e.g., gpt-4-turbo, claude-opus-4-6)',
                ])
                ->addColumn('task_type', 'string', [
                    'null' => true,
                    'limit' => 64,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Task type for this call (e.g., asset_lookup)',
                ])
                ->addColumn('input_tokens', 'integer', ['signed' => false, 'default' => 0])
                ->addColumn('output_tokens', 'integer', ['signed' => false, 'default' => 0])
                ->addColumn('latency_ms', 'integer', [
                    'signed' => false,
                    'default' => 0,
                    'comment' => 'Response time in milliseconds',
                ])
                ->addColumn('estimated_cost_eur', 'decimal', [
                    'precision' => 10,
                    'scale' => 6,
                    'default' => '0.000000',
                    'comment' => 'Estimated cost in EUR based on token usage',
                ])
                ->addColumn('users_userid', 'integer', [
                    'null' => true,
                    'signed' => false,
                    'comment' => 'User who triggered the call (null for automated)',
                ])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'], ['name' => 'idx_ai_usage_log_instance'])
                ->addIndex(['provider_id'], ['name' => 'idx_ai_usage_log_provider'])
                ->addIndex(['task_type'], ['name' => 'idx_ai_usage_log_task'])
                ->addIndex(['created_at'], ['name' => 'idx_ai_usage_log_created'])
                ->addIndex(['instances_id', 'created_at'], ['name' => 'idx_ai_usage_log_instance_created'])
                ->create();
        }

        // ── Table 4: AI Fallback Chain ──
        // Defines fallback order when primary provider fails
        if (!$this->hasTable('ai_fallback_chain')) {
            $table = $this->table('ai_fallback_chain', ['id' => false, 'primary_key' => ['id']]);
            $table->addColumn('id', 'integer', ['autoIncrement' => true, 'signed' => false])
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('task_type', 'string', [
                    'limit' => 64,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Task type for which this chain applies',
                ])
                ->addColumn('provider_id', 'integer', ['signed' => false])
                ->addColumn('fallback_order', 'integer', [
                    'default' => 0,
                    'comment' => 'Order in fallback chain (0=primary, 1=secondary, etc.)',
                ])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'], ['name' => 'idx_ai_fallback_chain_instance'])
                ->addIndex(['task_type'], ['name' => 'idx_ai_fallback_chain_task'])
                ->addIndex(['fallback_order'], ['name' => 'idx_ai_fallback_chain_order'])
                ->create();
        }
    }
}
