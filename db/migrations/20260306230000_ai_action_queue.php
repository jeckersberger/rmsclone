<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * AI Action Queue + Maintenance Tracking + Email AI fields
 *
 * ai_action_queue: Warteschlange fuer KI-Aktionen mit Bestaetigungs-Workflow
 *   - Eingehend (categorize_email, etc.) = auto_executed
 *   - Ausgehend (send_email, send_reminder) = pending -> approved/rejected
 *
 * maintenance_log: Wartungshistorie pro Asset
 *
 * Neue Felder auf emailReceived: AI-Kategorisierung
 * Neue Felder auf assetTypes: Wartungsintervall
 * Neue Felder auf assets: Letzte Wartung, Kaufdatum
 */
final class AiActionQueue extends AbstractMigration
{
    public function change(): void
    {
        // ═══ ai_action_queue ═══
        if (!$this->hasTable('ai_action_queue')) {
            $this->table('ai_action_queue')
                ->addColumn('instances_id', 'integer')
                ->addColumn('action_type', 'string', ['limit' => 50, 'comment' => 'send_email, categorize_email, flag_maintenance, etc.'])
                ->addColumn('direction', 'string', ['limit' => 10, 'default' => 'inbound', 'comment' => 'inbound = auto, outbound = needs approval'])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending', 'comment' => 'pending, approved, rejected, auto_executed, executed, error'])
                ->addColumn('title', 'string', ['limit' => 255])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('payload_json', 'text', ['null' => true, 'comment' => 'Aktion-Details als JSON'])
                ->addColumn('result_json', 'text', ['null' => true, 'comment' => 'Ergebnis nach Ausfuehrung'])
                ->addColumn('related_type', 'string', ['limit' => 50, 'null' => true, 'comment' => 'email, invoice, asset, project'])
                ->addColumn('related_id', 'integer', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('reviewed_at', 'timestamp', ['null' => true])
                ->addColumn('reviewed_by', 'integer', ['null' => true])
                ->addColumn('executed_at', 'timestamp', ['null' => true])
                ->addIndex(['instances_id', 'status'])
                ->addIndex(['instances_id', 'action_type'])
                ->addIndex(['instances_id', 'created_at'])
                ->addIndex(['related_type', 'related_id'])
                ->create();
        }

        // ═══ maintenance_log ═══
        if (!$this->hasTable('maintenance_log')) {
            $this->table('maintenance_log')
                ->addColumn('assets_id', 'integer')
                ->addColumn('performed_by', 'integer')
                ->addColumn('performed_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('notes', 'text', ['null' => true])
                ->addIndex(['assets_id'])
                ->create();
        }

        // ═══ assetTypes: Wartungsintervall ═══
        $assetTypes = $this->table('assetTypes');
        if (!$assetTypes->hasColumn('assetTypes_maintenanceInterval')) {
            $assetTypes->addColumn('assetTypes_maintenanceInterval', 'integer', [
                'null' => true,
                'comment' => 'Wartungsintervall in Tagen (null = keine Wartung)'
            ]);
            $assetTypes->update();
        }

        // ═══ assets: Wartung + Kauf ═══
        $assets = $this->table('assets');
        if (!$assets->hasColumn('assets_lastMaintenanceDate')) {
            $assets->addColumn('assets_lastMaintenanceDate', 'date', [
                'null' => true,
                'comment' => 'Datum der letzten Wartung'
            ]);
        }
        if (!$assets->hasColumn('assets_purchaseDate')) {
            $assets->addColumn('assets_purchaseDate', 'date', [
                'null' => true,
                'comment' => 'Kaufdatum'
            ]);
        }
        $assets->update();

        // ═══ emailReceived: KI-Felder ═══
        $email = $this->table('emailReceived');
        $emailAiCols = [
            'ai_processed' => ['type' => 'boolean', 'opts' => ['default' => 0]],
            'ai_processed_at' => ['type' => 'timestamp', 'opts' => ['null' => true]],
            'ai_category' => ['type' => 'string', 'opts' => ['limit' => 30, 'null' => true, 'comment' => 'anfrage, buchung, reklamation, rechnung, allgemein, spam']],
            'ai_priority' => ['type' => 'string', 'opts' => ['limit' => 10, 'null' => true, 'comment' => 'hoch, mittel, niedrig']],
            'ai_summary' => ['type' => 'text', 'opts' => ['null' => true, 'comment' => 'KI-generierte Zusammenfassung']],
        ];
        foreach ($emailAiCols as $colName => $def) {
            if (!$email->hasColumn($colName)) {
                $email->addColumn($colName, $def['type'], $def['opts']);
            }
        }
        $email->update();

        // ═══ cron_runs: falls noch nicht vorhanden ═══
        if (!$this->hasTable('cron_runs')) {
            $this->table('cron_runs')
                ->addColumn('instances_id', 'integer')
                ->addColumn('job_type', 'string', ['limit' => 50])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'success'])
                ->addColumn('items_processed', 'integer', ['default' => 0])
                ->addColumn('details_json', 'text', ['null' => true])
                ->addColumn('completed_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'job_type'])
                ->create();
        }
    }
}
