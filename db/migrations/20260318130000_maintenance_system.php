<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class MaintenanceSystem extends AbstractMigration
{
    /**
     * Wartungs- & Predictive Maintenance System
     * Erstellt Tabellen für:
     * - Wartungspläne (Schedules)
     * - Wartungsaufträge (Jobs)
     * - Wartungsfotos
     * - Checklisten
     * - Checklisten-Ergebnisse
     */
    public function change(): void
    {
        // maintenance_schedules - Wartungspläne
        if (!$this->hasTable('maintenance_schedules')) {
            $this->table('maintenance_schedules', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Wartungspläne für Assets oder Asset-Typen',
                'row_format' => 'DYNAMIC',
            ])
                ->addColumn('id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'identity' => 'enable',
                ])
                ->addColumn('asset_type_id', 'integer', [
                    'null' => true,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'comment' => 'Asset-Typ für standardisierte Wartung, NULL wenn für einzelnes Asset',
                ])
                ->addColumn('asset_id', 'integer', [
                    'null' => true,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'comment' => 'Einzelnes Asset, NULL wenn für alle vom Typ',
                ])
                ->addColumn('name', 'string', [
                    'null' => false,
                    'limit' => 255,
                    'collation' => 'utf8mb4_0900_ai_ci',
                    'encoding' => 'utf8mb4',
                    'comment' => 'Name des Wartungsplans (z.B. "Ölwechsel", "TÜV")',
                ])
                ->addColumn('description', 'text', [
                    'null' => true,
                    'collation' => 'utf8mb4_0900_ai_ci',
                    'encoding' => 'utf8mb4',
                    'comment' => 'Detaillierte Beschreibung der Wartung',
                ])
                ->addColumn('interval_type', 'enum', [
                    'null' => false,
                    'values' => ['days', 'months', 'uses', 'hours'],
                    'default' => 'months',
                    'comment' => 'Art des Wartungsintervalls',
                ])
                ->addColumn('interval_value', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'comment' => 'Wert des Intervalls (z.B. 12 Monate, 30 Tage)',
                ])
                ->addColumn('last_performed_at', 'datetime', [
                    'null' => true,
                    'comment' => 'Zeitstempel der letzten durchgeführten Wartung',
                ])
                ->addColumn('next_due_at', 'datetime', [
                    'null' => true,
                    'comment' => 'Nächster Fälligkeitszeitpunkt',
                ])
                ->addColumn('instances_id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('is_active', 'boolean', [
                    'null' => false,
                    'default' => 1,
                ])
                ->addColumn('created_at', 'datetime', [
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ])
                ->addColumn('updated_at', 'datetime', [
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                    'update' => 'CURRENT_TIMESTAMP',
                ])
                ->addIndex(['asset_type_id'], ['name' => 'idx_schedule_asset_type_id'])
                ->addIndex(['asset_id'], ['name' => 'idx_schedule_asset_id'])
                ->addIndex(['instances_id'], ['name' => 'idx_schedule_instances_id'])
                ->addIndex(['next_due_at'], ['name' => 'idx_schedule_next_due_at'])
                ->create();
        }

        // maintenance_jobs - Erweiterte Wartungsaufträge (neue Tabelle, komplementär zu maintenanceJobs)
        if (!$this->hasTable('maintenance_jobs')) {
            $this->table('maintenance_jobs', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Einzelne Wartungsaufträge (komplett strukturiert)',
                'row_format' => 'DYNAMIC',
            ])
                ->addColumn('id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'identity' => 'enable',
                ])
                ->addColumn('schedule_id', 'integer', [
                    'null' => true,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'comment' => 'Verknüpfung zu Wartungsplan (NULL für manuelle Jobs)',
                ])
                ->addColumn('asset_id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('title', 'string', [
                    'null' => false,
                    'limit' => 255,
                    'collation' => 'utf8mb4_0900_ai_ci',
                    'encoding' => 'utf8mb4',
                ])
                ->addColumn('description', 'text', [
                    'null' => true,
                    'collation' => 'utf8mb4_0900_ai_ci',
                    'encoding' => 'utf8mb4',
                ])
                ->addColumn('status', 'enum', [
                    'null' => false,
                    'values' => ['scheduled', 'in_progress', 'completed', 'cancelled'],
                    'default' => 'scheduled',
                ])
                ->addColumn('assigned_to', 'integer', [
                    'null' => true,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'comment' => 'Zugewiesener Techniker',
                ])
                ->addColumn('priority', 'enum', [
                    'null' => false,
                    'values' => ['low', 'medium', 'high', 'critical'],
                    'default' => 'medium',
                ])
                ->addColumn('estimated_cost', 'decimal', [
                    'null' => true,
                    'precision' => 10,
                    'scale' => 2,
                ])
                ->addColumn('actual_cost', 'decimal', [
                    'null' => true,
                    'precision' => 10,
                    'scale' => 2,
                ])
                ->addColumn('started_at', 'datetime', [
                    'null' => true,
                ])
                ->addColumn('completed_at', 'datetime', [
                    'null' => true,
                ])
                ->addColumn('completed_by', 'integer', [
                    'null' => true,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'comment' => 'Benutzer, der die Wartung abgeschlossen hat',
                ])
                ->addColumn('notes', 'text', [
                    'null' => true,
                    'collation' => 'utf8mb4_0900_ai_ci',
                    'encoding' => 'utf8mb4',
                    'comment' => 'Notizen/Erkenntnisse aus der Wartung',
                ])
                ->addColumn('instances_id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('created_at', 'datetime', [
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ])
                ->addColumn('updated_at', 'datetime', [
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                    'update' => 'CURRENT_TIMESTAMP',
                ])
                ->addIndex(['schedule_id'], ['name' => 'idx_job_schedule_id'])
                ->addIndex(['asset_id'], ['name' => 'idx_job_asset_id'])
                ->addIndex(['assigned_to'], ['name' => 'idx_job_assigned_to'])
                ->addIndex(['status'], ['name' => 'idx_job_status'])
                ->addIndex(['instances_id'], ['name' => 'idx_job_instances_id'])
                ->addIndex(['completed_at'], ['name' => 'idx_job_completed_at'])
                ->create();
        }

        // maintenance_job_photos - Fotos zu Wartungsaufträgen
        if (!$this->hasTable('maintenance_job_photos')) {
            $this->table('maintenance_job_photos', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Fotos dokumentierter Wartungsarbeiten',
                'row_format' => 'DYNAMIC',
            ])
                ->addColumn('id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'identity' => 'enable',
                ])
                ->addColumn('job_id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('file_path', 'string', [
                    'null' => false,
                    'limit' => 500,
                    'collation' => 'utf8mb4_0900_ai_ci',
                    'encoding' => 'utf8mb4',
                    'comment' => 'S3 Pfad oder lokaler Pfad zur Datei',
                ])
                ->addColumn('description', 'string', [
                    'null' => true,
                    'limit' => 255,
                    'collation' => 'utf8mb4_0900_ai_ci',
                    'encoding' => 'utf8mb4',
                ])
                ->addColumn('uploaded_by', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('created_at', 'datetime', [
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ])
                ->addIndex(['job_id'], ['name' => 'idx_photo_job_id'])
                ->addIndex(['uploaded_by'], ['name' => 'idx_photo_uploaded_by'])
                ->create();
        }

        // maintenance_checklists - Checklisten-Templates
        if (!$this->hasTable('maintenance_checklists')) {
            $this->table('maintenance_checklists', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Checklisten-Templates für Wartungen',
                'row_format' => 'DYNAMIC',
            ])
                ->addColumn('id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'identity' => 'enable',
                ])
                ->addColumn('name', 'string', [
                    'null' => false,
                    'limit' => 255,
                    'collation' => 'utf8mb4_0900_ai_ci',
                    'encoding' => 'utf8mb4',
                    'comment' => 'Name der Checkliste (z.B. "Monatliche Inspek")',
                ])
                ->addColumn('asset_type_id', 'integer', [
                    'null' => true,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'comment' => 'Asset-Typ für diese Checkliste, NULL = global',
                ])
                ->addColumn('instances_id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('items', 'json', [
                    'null' => true,
                    'comment' => 'JSON Array mit Checklisteneinträgen: [{"item":"Text","required":true}]',
                ])
                ->addColumn('created_at', 'datetime', [
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ])
                ->addIndex(['asset_type_id'], ['name' => 'idx_checklist_asset_type_id'])
                ->addIndex(['instances_id'], ['name' => 'idx_checklist_instances_id'])
                ->create();
        }

        // maintenance_checklist_results - Ausgefüllte Checklisten
        if (!$this->hasTable('maintenance_checklist_results')) {
            $this->table('maintenance_checklist_results', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'comment' => 'Ausgefüllte Checklisten für Wartungsaufträge',
                'row_format' => 'DYNAMIC',
            ])
                ->addColumn('id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                    'identity' => 'enable',
                ])
                ->addColumn('job_id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('checklist_id', 'integer', [
                    'null' => false,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('results', 'json', [
                    'null' => true,
                    'comment' => 'JSON mit Ergebnissen: [{"item":"Text","checked":true,"notes":"..."}]',
                ])
                ->addColumn('completed_by', 'integer', [
                    'null' => true,
                    'limit' => MysqlAdapter::INT_REGULAR,
                ])
                ->addColumn('completed_at', 'datetime', [
                    'null' => true,
                ])
                ->addIndex(['job_id'], ['name' => 'idx_checklist_result_job_id'])
                ->addIndex(['checklist_id'], ['name' => 'idx_checklist_result_checklist_id'])
                ->create();
        }
    }
}
