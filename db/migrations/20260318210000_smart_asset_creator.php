<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * Smart Asset Creator (I9) - AI-powered asset data lookup and enrichment
 *
 * Creates tables for:
 * - Caching AI lookup results (manufacturer + model)
 * - Learning from user corrections
 */
final class SmartAssetCreator extends AbstractMigration
{
    public function change(): void
    {
        // ── Asset Lookup Cache ──
        // Stores AI lookup results for manufacturer/model combinations
        // 30-day TTL for automatic refresh
        if (!$this->hasTable('asset_lookup_cache')) {
            $this->table('asset_lookup_cache', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('manufacturer', 'string', [
                    'limit' => 255,
                    'collation' => 'utf8mb4_unicode_ci',
                ])
                ->addColumn('model', 'string', [
                    'limit' => 255,
                    'collation' => 'utf8mb4_unicode_ci',
                ])
                ->addColumn('ean', 'string', [
                    'limit' => 50,
                    'null' => true,
                ])
                ->addColumn('data_json', 'text', [
                    'limit' => MysqlAdapter::TEXT_LONG,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Complete asset data as JSON: name, weight_kg, dimensions, power_watts, etc.',
                ])
                ->addColumn('sources', 'json', [
                    'null' => true,
                    'comment' => 'Per-field sources: {"field_name": "ai|db|web", "confidence": 0.95}',
                ])
                ->addColumn('confidence', 'decimal', [
                    'precision' => 3,
                    'scale' => 2,
                    'comment' => 'Overall confidence score (0.00 - 1.00)',
                ])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('expires_at', 'date', [
                    'comment' => 'Cache expires after 30 days for auto-refresh',
                ])
                ->addIndex(['instances_id', 'manufacturer', 'model'], ['name' => 'idx_lookup_cache_key'])
                ->addIndex(['expires_at'], ['name' => 'idx_lookup_cache_expires'])
                ->create();
        }

        // ── Asset Lookup Corrections ──
        // User corrections for ML feedback loop
        if (!$this->hasTable('asset_lookup_corrections')) {
            $this->table('asset_lookup_corrections', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => false, 'null' => false])
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('cache_id', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('field_name', 'string', [
                    'limit' => 100,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'Field that was corrected (e.g., "weight_kg", "power_watts")',
                ])
                ->addColumn('original_value', 'text', [
                    'null' => true,
                    'collation' => 'utf8mb4_unicode_ci',
                ])
                ->addColumn('corrected_value', 'text', [
                    'collation' => 'utf8mb4_unicode_ci',
                ])
                ->addColumn('corrected_by', 'integer', ['signed' => false, 'null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'field_name'], ['name' => 'idx_corrections_field'])
                ->addIndex(['created_at'], ['name' => 'idx_corrections_date'])
                ->create();
        }

        // ── Smart Lookup Job Queue ──
        // For bulk lookups running in background
        if (!$this->hasTable('smart_lookup_jobs')) {
            $this->table('smart_lookup_jobs', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'string', [
                    'limit' => 64,
                    'null' => false,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'UUID job ID',
                ])
                ->addColumn('instances_id', 'integer', ['signed' => false])
                ->addColumn('status', 'enum', [
                    'values' => ['pending', 'processing', 'completed', 'failed'],
                    'default' => 'pending',
                ])
                ->addColumn('items_count', 'integer', ['signed' => false])
                ->addColumn('completed_count', 'integer', ['default' => 0, 'signed' => false])
                ->addColumn('payload', 'json', [
                    'comment' => 'Original request payload: {items: [...]}',
                ])
                ->addColumn('results', 'text', [
                    'limit' => MysqlAdapter::TEXT_LONG,
                    'null' => true,
                    'collation' => 'utf8mb4_unicode_ci',
                    'comment' => 'JSON array of AssetLookupResult objects',
                ])
                ->addColumn('error_message', 'text', ['null' => true])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('started_at', 'datetime', ['null' => true])
                ->addColumn('completed_at', 'datetime', ['null' => true])
                ->addIndex(['instances_id', 'status'], ['name' => 'idx_jobs_status'])
                ->addIndex(['created_at'], ['name' => 'idx_jobs_created'])
                ->create();
        }
    }
}
