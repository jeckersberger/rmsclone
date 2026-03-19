<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * KI-Anonymisierung (I6) - PII Protection for Cloud AI Requests
 *
 * Creates configuration and logging tables for anonymizing sensitive data
 * before sending to cloud AI providers. Never stores actual PII in logs.
 */
class AiAnonymization extends AbstractMigration
{
    public function change(): void
    {
        // ── Anonymization Configuration ──
        if (!$this->hasTable('ai_anonymization_config')) {
            $this->table('ai_anonymization_config', ['id' => false, 'primary_key' => ['instances_id']])
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('mode', 'enum', [
                    'values' => ['strict', 'standard', 'minimal', 'off'],
                    'default' => 'strict'
                ])
                ->addColumn('custom_rules', 'json', ['null' => true])
                ->addColumn('provider_overrides', 'json', ['null' => true])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['mode'])
                ->create();
        }

        // ── Anonymization Audit Log ──
        // CRITICAL: Never stores actual PII. Only stores replacement counts and types.
        if (!$this->hasTable('ai_anonymization_log')) {
            $this->table('ai_anonymization_log', ['id' => true, 'signed' => false])
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('request_id', 'varchar', ['limit' => 64, 'null' => true])
                ->addColumn('replacements_count', 'integer', ['default' => 0, 'signed' => false])
                ->addColumn('replacement_types', 'json', [
                    'comment' => 'e.g., {"PERSON": 3, "EMAIL": 2, "IBAN": 1} - never actual values'
                ])
                ->addColumn('provider', 'varchar', ['limit' => 50])
                ->addColumn('mode', 'enum', ['values' => ['strict', 'standard', 'minimal', 'off']])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'created_at'])
                ->addIndex(['provider'])
                ->addIndex(['mode'])
                ->create();
        }
    }
}
