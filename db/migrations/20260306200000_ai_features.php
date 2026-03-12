<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * AI Features: Settings, expense receipts with local file storage,
 * AI usage log, and contract analysis.
 */
final class AiFeatures extends AbstractMigration
{
    public function change(): void
    {
        // --- instances: AI configuration fields ---
        $instances = $this->table('instances');
        $aiCols = [
            'instances_aiEnabled' => ['type' => 'boolean', 'opts' => [
                'default' => 0,
                'comment' => 'KI-Features global aktiviert'
            ]],
            'instances_aiApiKey' => ['type' => 'string', 'opts' => [
                'limit' => 255, 'null' => true,
                'comment' => 'Claude API Key (verschluesselt)'
            ]],
            'instances_aiModel' => ['type' => 'string', 'opts' => [
                'limit' => 60, 'default' => 'claude-haiku-4-5-20251001',
                'comment' => 'Standard-KI-Modell'
            ]],
            'instances_aiFeatureInvoiceScan' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: PDF-Rechnungen auslesen'
            ]],
            'instances_aiFeatureSearch' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: Intelligente Suche'
            ]],
            'instances_aiFeatureEmailDraft' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: E-Mail-Entwuerfe'
            ]],
            'instances_aiFeatureQuoteAssist' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: Angebots-Assistent'
            ]],
            'instances_aiFeatureProjectSummary' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: Projekt-Zusammenfassungen'
            ]],
            'instances_aiFeatureExpenseCategory' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: Ausgaben-Kategorisierung'
            ]],
            'instances_aiFeatureContractAnalysis' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: Vertragsanalyse'
            ]],
            'instances_aiFeaturePriceSuggestion' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: Preisvorschlaege'
            ]],
            'instances_aiFeatureDamageReport' => ['type' => 'boolean', 'opts' => [
                'default' => 1, 'comment' => 'Feature: Schadensbericht aus Foto'
            ]],
        ];

        foreach ($aiCols as $colName => $def) {
            if (!$instances->hasColumn($colName)) {
                $instances->addColumn($colName, $def['type'], $def['opts']);
            }
        }
        $instances->update();

        // --- expense_receipts: local file uploads for incoming invoices ---
        if (!$this->hasTable('expense_receipts')) {
            $this->table('expense_receipts')
                ->addColumn('instances_id', 'integer')
                ->addColumn('file_path', 'string', ['limit' => 500, 'comment' => 'Lokaler Dateipfad'])
                ->addColumn('original_name', 'string', ['limit' => 255])
                ->addColumn('mime_type', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('file_size', 'integer', ['null' => true])
                ->addColumn('vendor_name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('invoice_number', 'string', ['limit' => 100, 'null' => true])
                ->addColumn('invoice_date', 'date', ['null' => true])
                ->addColumn('net_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                ->addColumn('vat_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                ->addColumn('gross_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
                ->addColumn('vat_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
                ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'EUR'])
                ->addColumn('euer_categories_id', 'integer', ['null' => true])
                ->addColumn('euer_bookings_id', 'integer', ['null' => true, 'comment' => 'Verknuepfter EUeR-Buchungssatz'])
                ->addColumn('ai_extracted_json', 'text', ['null' => true, 'comment' => 'Rohe KI-Extraktion als JSON'])
                ->addColumn('ai_confidence', 'decimal', ['precision' => 3, 'scale' => 2, 'null' => true])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending', 'comment' => 'pending, extracted, confirmed, booked'])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('uploaded_by', 'integer')
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['status'])
                ->create();
        }

        // --- ai_usage_log: Track API usage and costs ---
        if (!$this->hasTable('ai_usage_log')) {
            $this->table('ai_usage_log')
                ->addColumn('instances_id', 'integer')
                ->addColumn('feature', 'string', ['limit' => 50, 'comment' => 'invoice_scan, search, email_draft, etc.'])
                ->addColumn('model', 'string', ['limit' => 60])
                ->addColumn('input_tokens', 'integer', ['default' => 0])
                ->addColumn('output_tokens', 'integer', ['default' => 0])
                ->addColumn('cost_estimate_usd', 'decimal', ['precision' => 8, 'scale' => 6, 'default' => 0])
                ->addColumn('users_userid', 'integer')
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'feature'])
                ->addIndex(['instances_id', 'created_at'])
                ->create();
        }

        // --- contracts: uploaded contracts for AI analysis ---
        if (!$this->hasTable('contracts')) {
            $this->table('contracts')
                ->addColumn('instances_id', 'integer')
                ->addColumn('projects_id', 'integer', ['null' => true])
                ->addColumn('clients_id', 'integer', ['null' => true])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('file_path', 'string', ['limit' => 500])
                ->addColumn('original_name', 'string', ['limit' => 255])
                ->addColumn('ai_summary', 'text', ['null' => true])
                ->addColumn('ai_key_points_json', 'text', ['null' => true])
                ->addColumn('uploaded_by', 'integer')
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->create();
        }
    }
}
