<?php
/**
 * MaintenanceService - Wartungs- & Predictive Maintenance System
 *
 * Verwaltet:
 * - Wartungspläne (Schedules) für Assets/Asset-Typen
 * - Wartungsaufträge (Jobs) mit Status-Management
 * - Wartungsfotos und Dokumentation
 * - Checklisten für standardisierte Wartungsprozesse
 * - Kostenüberwachung
 * - Automatische Job-Erstellung nach Zeitplan
 * - Dashboard-Statistiken
 */
class MaintenanceService
{
    private $db;

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    const INTERVAL_DAYS = 'days';
    const INTERVAL_MONTHS = 'months';
    const INTERVAL_USES = 'uses';
    const INTERVAL_HOURS = 'hours';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Wartungspläne für einzelnes Asset abrufen
     */
    public function getSchedulesForAsset(int $assetId, int $instanceId): array
    {
        $this->db->where('ms.asset_id', $assetId);
        $this->db->where('ms.instances_id', $instanceId);
        $this->db->where('ms.is_active', 1);
        $this->db->orderBy('ms.next_due_at', 'ASC');
        $schedules = $this->db->get('maintenance_schedules ms', null, [
            'ms.*'
        ]) ?: [];

        return $schedules;
    }

    /**
     * Wartungspläne für Asset-Typ abrufen (standard für all solche Assets)
     */
    public function getSchedulesForAssetType(int $assetTypeId, int $instanceId): array
    {
        $this->db->where('ms.asset_type_id', $assetTypeId);
        $this->db->where('ms.asset_id', null);
        $this->db->where('ms.instances_id', $instanceId);
        $this->db->where('ms.is_active', 1);
        $this->db->orderBy('ms.next_due_at', 'ASC');
        $schedules = $this->db->get('maintenance_schedules ms', null, [
            'ms.*'
        ]) ?: [];

        return $schedules;
    }

    /**
     * Neuen Wartungsplan erstellen
     */
    public function createSchedule(array $data): int
    {
        $scheduleId = $this->db->insert('maintenance_schedules', [
            'asset_type_id' => $data['asset_type_id'] ?? null,
            'asset_id' => $data['asset_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'interval_type' => $data['interval_type'] ?? self::INTERVAL_MONTHS,
            'interval_value' => (int) $data['interval_value'],
            'instances_id' => $data['instances_id'],
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // next_due_at berechnen
        if ($scheduleId) {
            $nextDue = $this->calculateNextDueDate($data['interval_type'] ?? self::INTERVAL_MONTHS, $data['interval_value'] ?? 30);
            $this->db->where('id', $scheduleId);
            $this->db->update('maintenance_schedules', ['next_due_at' => $nextDue]);
        }

        return $scheduleId ?: 0;
    }

    /**
     * Wartungsplan aktualisieren
     */
    public function updateSchedule(int $id, array $data): bool
    {
        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['interval_type'])) $updateData['interval_type'] = $data['interval_type'];
        if (isset($data['interval_value'])) $updateData['interval_value'] = (int) $data['interval_value'];
        if (isset($data['is_active'])) $updateData['is_active'] = (int) $data['is_active'];
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $id);
        return $this->db->update('maintenance_schedules', $updateData);
    }

    /**
     * Wartungsplan löschen
     */
    public function deleteSchedule(int $id): bool
    {
        $this->db->where('id', $id);
        return $this->db->delete('maintenance_schedules');
    }

    /**
     * Alle überfälligen Wartungen abrufen
     */
    public function getOverdueMaintenances(int $instanceId): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->where('ms.instances_id', $instanceId);
        $this->db->where('ms.is_active', 1);
        $this->db->where('ms.next_due_at', $now, '<=');
        $this->db->join('assetTypes at', 'a.assetTypes_id = at.assetTypes_id', 'LEFT');
        $this->db->join('assets a', 'ms.asset_id = a.assets_id OR (ms.asset_id IS NULL AND a.assetTypes_id = ms.asset_type_id)', 'LEFT');
        $this->db->orderBy('ms.next_due_at', 'ASC');

        return $this->db->get('maintenance_schedules ms', null, [
            'ms.*', 'a.assets_id', 'a.assets_tag', 'at.assetTypes_name'
        ]) ?: [];
    }

    /**
     * Bevorstehende Wartungen abrufen
     */
    public function getUpcomingMaintenances(int $instanceId, int $daysAhead = 30): array
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', strtotime("+{$daysAhead} days"));

        $this->db->where('ms.instances_id', $instanceId);
        $this->db->where('ms.is_active', 1);
        $this->db->where('ms.next_due_at', $now, '>');
        $this->db->where('ms.next_due_at', $future, '<=');
        $this->db->join('assetTypes at', 'a.assetTypes_id = at.assetTypes_id', 'LEFT');
        $this->db->join('assets a', 'ms.asset_id = a.assets_id OR (ms.asset_id IS NULL AND a.assetTypes_id = ms.asset_type_id)', 'LEFT');
        $this->db->orderBy('ms.next_due_at', 'ASC');

        return $this->db->get('maintenance_schedules ms', null, [
            'ms.*', 'a.assets_id', 'a.assets_tag', 'at.assetTypes_name'
        ]) ?: [];
    }

    /**
     * Neuen Wartungsauftrag erstellen
     */
    public function createJob(array $data): int
    {
        $jobId = $this->db->insert('maintenance_jobs', [
            'schedule_id' => $data['schedule_id'] ?? null,
            'asset_id' => $data['asset_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? self::STATUS_SCHEDULED,
            'assigned_to' => $data['assigned_to'] ?? null,
            'priority' => $data['priority'] ?? self::PRIORITY_MEDIUM,
            'estimated_cost' => isset($data['estimated_cost']) ? floatval($data['estimated_cost']) : null,
            'instances_id' => $data['instances_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $jobId ?: 0;
    }

    /**
     * Status eines Wartungsauftrags aktualisieren
     */
    public function updateJobStatus(int $jobId, string $status, ?int $userId = null): bool
    {
        $updateData = ['status' => $status];

        if ($status === self::STATUS_IN_PROGRESS) {
            $updateData['started_at'] = date('Y-m-d H:i:s');
        } elseif ($status === self::STATUS_COMPLETED) {
            $updateData['completed_at'] = date('Y-m-d H:i:s');
            if ($userId) {
                $updateData['completed_by'] = $userId;
            }
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $jobId);
        return $this->db->update('maintenance_jobs', $updateData);
    }

    /**
     * Wartungsauftrag mit allen Details abrufen
     */
    public function getJob(int $jobId): ?array
    {
        $this->db->where('mj.id', $jobId);
        $job = $this->db->getOne('maintenance_jobs mj', null, ['mj.*']);

        if (!$job) return null;

        // Fotos laden
        $this->db->where('job_id', $jobId);
        $job['photos'] = $this->db->get('maintenance_job_photos', null, [
            'id', 'file_path', 'description', 'uploaded_by', 'created_at'
        ]) ?: [];

        // Checklisten-Ergebnisse laden
        $this->db->where('job_id', $jobId);
        $job['checklist_results'] = $this->db->get('maintenance_checklist_results', null, [
            'id', 'checklist_id', 'results', 'completed_by', 'completed_at'
        ]) ?: [];

        return $job;
    }

    /**
     * Service-Geschichte für ein Asset
     */
    public function getJobsForAsset(int $assetId, int $instanceId): array
    {
        $this->db->where('mj.asset_id', $assetId);
        $this->db->where('mj.instances_id', $instanceId);
        $this->db->orderBy('mj.created_at', 'DESC');
        $jobs = $this->db->get('maintenance_jobs mj', null, [
            'mj.*'
        ]) ?: [];

        return $jobs;
    }

    /**
     * Offene Aufträge abrufen
     */
    public function getOpenJobs(int $instanceId, ?string $priority = null): array
    {
        $this->db->where('mj.instances_id', $instanceId);
        $this->db->where('mj.status', self::STATUS_COMPLETED, '!=');
        $this->db->where('mj.status', self::STATUS_CANCELLED, '!=');

        if ($priority) {
            $this->db->where('mj.priority', $priority);
        }

        $this->db->join('assetTypes at', 'a.assetTypes_id = at.assetTypes_id', 'LEFT');
        $this->db->join('assets a', 'mj.asset_id = a.assets_id', 'LEFT');
        $this->db->orderBy('mj.priority', 'ASC');
        $this->db->orderBy('mj.created_at', 'DESC');

        return $this->db->get('maintenance_jobs mj', null, [
            'mj.*', 'a.assets_tag', 'at.assetTypes_name'
        ]) ?: [];
    }

    /**
     * Foto zu Wartungsauftrag hinzufügen
     */
    public function addJobPhoto(int $jobId, string $filePath, ?string $description, int $userId): int
    {
        $photoId = $this->db->insert('maintenance_job_photos', [
            'job_id' => $jobId,
            'file_path' => $filePath,
            'description' => $description,
            'uploaded_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $photoId ?: 0;
    }

    /**
     * Alle Checklisten abrufen
     */
    public function getChecklists(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('name', 'ASC');
        return $this->db->get('maintenance_checklists', null, [
            'id', 'name', 'asset_type_id', 'items'
        ]) ?: [];
    }

    /**
     * Neue Checkliste erstellen
     */
    public function createChecklist(array $data): int
    {
        $items = $data['items'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        $checklistId = $this->db->insert('maintenance_checklists', [
            'name' => $data['name'],
            'asset_type_id' => $data['asset_type_id'] ?? null,
            'instances_id' => $data['instances_id'],
            'items' => json_encode($items),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $checklistId ?: 0;
    }

    /**
     * Checkliste abschließen (Ergebnisse speichern)
     */
    public function completeChecklist(int $jobId, int $checklistId, array $results, int $userId): int
    {
        $resultId = $this->db->insert('maintenance_checklist_results', [
            'job_id' => $jobId,
            'checklist_id' => $checklistId,
            'results' => json_encode($results),
            'completed_by' => $userId,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        return $resultId ?: 0;
    }

    /**
     * Wartungskosten für ein Asset über seinen Lebenszyklus
     */
    public function getMaintenanceCostsByAsset(int $assetId, int $instanceId): array
    {
        $this->db->where('mj.asset_id', $assetId);
        $this->db->where('mj.instances_id', $instanceId);
        $this->db->where('mj.status', self::STATUS_COMPLETED);
        $jobs = $this->db->get('maintenance_jobs mj', null, [
            'id', 'title', 'estimated_cost', 'actual_cost', 'completed_at'
        ]) ?: [];

        $totalEstimated = 0;
        $totalActual = 0;
        foreach ($jobs as $job) {
            $totalEstimated += floatval($job['estimated_cost'] ?? 0);
            $totalActual += floatval($job['actual_cost'] ?? 0);
        }

        return [
            'jobs' => $jobs,
            'total_estimated' => $totalEstimated,
            'total_actual' => $totalActual,
        ];
    }

    /**
     * CRON: Automatisch Jobs für überfällige Wartungspläne erstellen
     */
    public function checkAndCreateScheduledJobs(int $instanceId): int
    {
        $created = 0;
        $overdue = $this->getOverdueMaintenances($instanceId);

        foreach ($overdue as $schedule) {
            // Prüfen, ob bereits ein offener Job existiert
            $this->db->where('schedule_id', $schedule['id']);
            $this->db->where('status', self::STATUS_COMPLETED, '!=');
            $existingJob = $this->db->getOne('maintenance_jobs');

            if (!$existingJob) {
                // Asset bestimmen
                $assetId = $schedule['asset_id'];
                if (!$assetId && $schedule['asset_type_id']) {
                    // Für Asset-Typ: Erste Asset dieses Typs finden
                    $this->db->where('assetTypes_id', $schedule['asset_type_id']);
                    $this->db->where('instances_id', $instanceId);
                    $this->db->where('assets_deleted', 0);
                    $asset = $this->db->getOne('assets', null, ['assets_id']);
                    $assetId = $asset['assets_id'] ?? null;
                }

                if ($assetId) {
                    $this->createJob([
                        'schedule_id' => $schedule['id'],
                        'asset_id' => $assetId,
                        'title' => 'Geplant: ' . $schedule['name'],
                        'description' => $schedule['description'],
                        'priority' => self::PRIORITY_MEDIUM,
                        'status' => self::STATUS_SCHEDULED,
                        'instances_id' => $instanceId,
                    ]);
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Wartungsstatus für UI-Badge
     */
    public function getMaintenanceStatus(int $assetId): string
    {
        $this->db->where('ms.asset_id', $assetId);
        $this->db->where('ms.is_active', 1);
        $schedule = $this->db->getOne('maintenance_schedules ms', null, ['ms.next_due_at']);

        if (!$schedule || !$schedule['next_due_at']) {
            return 'ok';
        }

        $now = new DateTime();
        $nextDue = new DateTime($schedule['next_due_at']);
        $daysUntil = $now->diff($nextDue)->days;

        if ($nextDue < $now) {
            return 'overdue';
        } elseif ($daysUntil <= 7) {
            return 'due_soon';
        }

        return 'ok';
    }

    /**
     * Dashboard-Statistiken
     */
    public function getDashboardStats(int $instanceId): array
    {
        // Überfällige Wartungen
        $overdueCount = count($this->getOverdueMaintenances($instanceId));

        // Bevorstehend (nächste 30 Tage)
        $upcomingCount = count($this->getUpcomingMaintenances($instanceId, 30));

        // Offene Jobs
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', self::STATUS_COMPLETED, '!=');
        $this->db->where('status', self::STATUS_CANCELLED, '!=');
        $openJobsCount = $this->db->getValue('maintenance_jobs', 'COUNT(*)');

        // Kosten diesen Monat
        $thisMonth = date('Y-m-01');
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', self::STATUS_COMPLETED);
        $this->db->where('completed_at', $thisMonth, '>=');
        $result = $this->db->rawQuery(
            "SELECT SUM(actual_cost) as total FROM maintenance_jobs
             WHERE instances_id = ? AND status = ? AND completed_at >= ?",
            [$instanceId, self::STATUS_COMPLETED, $thisMonth . ' 00:00:00']
        );
        $costThisMonth = ($result[0]['total'] ?? 0);

        return [
            'overdue_count' => $overdueCount,
            'upcoming_count' => $upcomingCount,
            'open_jobs_count' => $openJobsCount,
            'cost_this_month' => floatval($costThisMonth),
        ];
    }

    /**
     * Hilfsfunktion: Nächsten Fälligkeitsdatum berechnen
     */
    private function calculateNextDueDate(string $intervalType, int $intervalValue): string
    {
        $now = new DateTime();

        switch ($intervalType) {
            case self::INTERVAL_DAYS:
                $now->add(new DateInterval('P' . $intervalValue . 'D'));
                break;
            case self::INTERVAL_MONTHS:
                $now->add(new DateInterval('P' . $intervalValue . 'M'));
                break;
            case self::INTERVAL_HOURS:
                $now->add(new DateInterval('PT' . $intervalValue . 'H'));
                break;
            case self::INTERVAL_USES:
                // Uses sind ereignisbasiert, standardmäßig 90 Tage
                $now->add(new DateInterval('P90D'));
                break;
        }

        return $now->format('Y-m-d H:i:s');
    }
}
