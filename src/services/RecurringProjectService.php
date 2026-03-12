<?php
require_once __DIR__ . '/AvailabilityService.php';

/**
 * Wiederkehrende Projekte
 *
 * Automatische Erstellung von Projekten basierend auf Vorlagen:
 * - Taeglich, woechentlich, monatlich
 * - Equipment + Crew-Uebernahme
 * - Automatische Datum-Berechnung
 *
 * Tabellen:
 *   recurring_project_templates  - Vorlagen fuer wiederkehrende Projekte
 */
class RecurringProjectService
{
    private $db;
    private ?AvailabilityService $availabilityService = null;

    public function __construct($db)
    {
        $this->db = $db;
        $this->availabilityService = new AvailabilityService($db);
    }

    /**
     * Create a recurring project template from an existing project
     */
    public function createTemplate(int $instanceId, int $sourceProjectId, array $schedule, int $userId): int
    {
        $this->db->where('projects_id', $sourceProjectId);
        $this->db->where('instances_id', $instanceId);
        $source = $this->db->getOne('projects');
        if (!$source) return 0;

        return $this->db->insert('recurring_project_templates', [
            'instances_id' => $instanceId,
            'source_project_id' => $sourceProjectId,
            'template_name' => $schedule['name'] ?? $source['projects_name'] . ' (Vorlage)',
            'recurrence_type' => $schedule['type'], // daily, weekly, biweekly, monthly
            'recurrence_day' => $schedule['day'] ?? null, // day of week (0-6) or day of month (1-31)
            'recurrence_time_start' => $schedule['time_start'] ?? null,
            'recurrence_time_end' => $schedule['time_end'] ?? null,
            'duration_hours' => $schedule['duration_hours'] ?? 24,
            'delivery_buffer_hours' => $schedule['delivery_buffer_hours'] ?? 2,
            'clone_assets' => $schedule['clone_assets'] ?? 1,
            'clone_crew' => $schedule['clone_crew'] ?? 0,
            'auto_create_days_ahead' => $schedule['auto_create_days_ahead'] ?? 7,
            'active' => 1,
            'created_by' => $userId,
        ]);
    }

    /**
     * Get all templates for an instance
     */
    public function getTemplates(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->join('projects', 'recurring_project_templates.source_project_id = projects.projects_id', 'LEFT');
        $this->db->orderBy('recurring_project_templates.template_name', 'ASC');
        return $this->db->get('recurring_project_templates', null, [
            'recurring_project_templates.*', 'projects.projects_name as source_name'
        ]) ?: [];
    }

    /**
     * Generate upcoming projects from templates
     * Called by cron or dashboard
     */
    public function generateUpcoming(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('active', 1);
        $this->db->where('deleted', 0);
        $templates = $this->db->get('recurring_project_templates') ?: [];

        $created = [];
        foreach ($templates as $t) {
            $nextDates = $this->calculateNextDates($t);
            foreach ($nextDates as $date) {
                // Check if project already exists for this date
                $checkName = $t['template_name'] . ' - ' . date('d.m.Y', strtotime($date['start']));
                $this->db->where('projects_name', $checkName);
                $this->db->where('instances_id', $instanceId);
                $this->db->where('projects_deleted', 0);
                if ($this->db->getOne('projects')) continue; // Already exists

                // Verfuegbarkeits-Check: Alle Assets der Vorlage pruefen
                $availabilityResult = $this->checkTemplateAvailability($t, $date);
                if (!$availabilityResult['available']) {
                    $created[] = [
                        'projects_id' => 0,
                        'name' => $checkName,
                        'date' => $date['start'],
                        'skipped' => true,
                        'reason' => 'Equipment nicht verfuegbar',
                        'conflicts' => $availabilityResult['conflicts'],
                    ];
                    continue;
                }

                // Create the project
                $projectId = $this->createProjectFromTemplate($t, $date, $instanceId);
                if ($projectId) {
                    $created[] = [
                        'projects_id' => $projectId,
                        'name' => $checkName,
                        'date' => $date['start'],
                    ];
                }
            }
        }

        return $created;
    }

    /**
     * Calculate next occurrence dates
     */
    private function calculateNextDates(array $template): array
    {
        $dates = [];
        $daysAhead = $template['auto_create_days_ahead'];
        $now = new DateTime();
        $end = (new DateTime())->modify("+{$daysAhead} days");

        switch ($template['recurrence_type']) {
            case 'daily':
                $current = clone $now;
                while ($current <= $end) {
                    $dates[] = $this->buildDateRange($current, $template);
                    $current->modify('+1 day');
                }
                break;

            case 'weekly':
                $dayOfWeek = $template['recurrence_day'] ?? 1; // Default Monday
                $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $current = clone $now;
                $current->modify("next {$dayNames[$dayOfWeek]}");
                while ($current <= $end) {
                    $dates[] = $this->buildDateRange($current, $template);
                    $current->modify('+1 week');
                }
                break;

            case 'biweekly':
                $dayOfWeek = $template['recurrence_day'] ?? 1;
                $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $current = clone $now;
                $current->modify("next {$dayNames[$dayOfWeek]}");
                while ($current <= $end) {
                    $dates[] = $this->buildDateRange($current, $template);
                    $current->modify('+2 weeks');
                }
                break;

            case 'monthly':
                $dayOfMonth = $template['recurrence_day'] ?? 1;
                $current = clone $now;
                $current->setDate($current->format('Y'), $current->format('m'), min($dayOfMonth, $current->format('t')));
                if ($current < $now) $current->modify('+1 month');
                while ($current <= $end) {
                    $dates[] = $this->buildDateRange($current, $template);
                    $current->modify('+1 month');
                }
                break;
        }

        return $dates;
    }

    private function buildDateRange(DateTime $date, array $template): array
    {
        $start = clone $date;
        if ($template['recurrence_time_start']) {
            $start = DateTime::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $template['recurrence_time_start']);
        }
        $end = clone $start;
        $end->modify('+' . ($template['duration_hours'] ?: 24) . ' hours');

        $deliverStart = clone $start;
        $deliverStart->modify('-' . ($template['delivery_buffer_hours'] ?: 2) . ' hours');
        $deliverEnd = clone $end;
        $deliverEnd->modify('+' . ($template['delivery_buffer_hours'] ?: 2) . ' hours');

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'deliver_start' => $deliverStart->format('Y-m-d H:i:s'),
            'deliver_end' => $deliverEnd->format('Y-m-d H:i:s'),
        ];
    }

    private function createProjectFromTemplate(array $template, array $dates, int $instanceId): int
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projectsStatuses_deleted', 0);
        $this->db->orderBy('projectsStatuses_rank', 'ASC');
        $statusId = $this->db->getValue('projectsStatuses', 'projectsStatuses_id', 1);

        $this->db->where('projects_id', $template['source_project_id']);
        $source = $this->db->getOne('projects');
        if (!$source) return 0;

        $name = $template['template_name'] . ' - ' . date('d.m.Y', strtotime($dates['start']));

        $projectId = $this->db->insert('projects', [
            'projects_name' => $name,
            'instances_id' => $instanceId,
            'projects_description' => $source['projects_description'],
            'projects_created' => date('Y-m-d H:i:s'),
            'projects_manager' => $source['projects_manager'] ?: $template['created_by'],
            'projectsTypes_id' => $source['projectsTypes_id'],
            'projectsStatuses_id' => $statusId,
            'clients_id' => $source['clients_id'],
            'locations_id' => $source['locations_id'],
            'projects_dates_use_start' => $dates['start'],
            'projects_dates_use_end' => $dates['end'],
            'projects_dates_deliver_start' => $dates['deliver_start'],
            'projects_dates_deliver_end' => $dates['deliver_end'],
        ]);

        if (!$projectId) return 0;

        // Clone assets
        if ($template['clone_assets']) {
            $this->db->where('projects_id', $template['source_project_id']);
            $this->db->where('assetsAssignments_deleted', 0);
            $assets = $this->db->get('assetsAssignments', null, [
                'assets_id', 'assetsAssignments_customPrice', 'assetsAssignments_discount', 'assetsAssignments_comment'
            ]);
            foreach (($assets ?: []) as $a) {
                $this->db->insert('assetsAssignments', [
                    'projects_id' => $projectId,
                    'assets_id' => $a['assets_id'],
                    'assetsAssignments_customPrice' => $a['assetsAssignments_customPrice'],
                    'assetsAssignments_discount' => $a['assetsAssignments_discount'],
                    'assetsAssignments_comment' => $a['assetsAssignments_comment'],
                    'assetsAssignments_timestamp' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $projectId;
    }

    /**
     * Prueft ob alle Assets einer Vorlage fuer den Zeitraum verfuegbar sind
     */
    public function checkTemplateAvailability(array $template, array $dates): array
    {
        if (!$template['clone_assets']) {
            return ['available' => true, 'conflicts' => []];
        }

        $this->db->where('projects_id', $template['source_project_id']);
        $this->db->where('assetsAssignments_deleted', 0);
        $assets = $this->db->get('assetsAssignments', null, ['assets_id']) ?: [];

        $allConflicts = [];
        foreach ($assets as $a) {
            $conflicts = $this->availabilityService->getAssetConflicts(
                (int)$a['assets_id'],
                $dates['start'],
                $dates['end']
            );
            if (!empty($conflicts)) {
                $allConflicts[$a['assets_id']] = $conflicts;
            }
        }

        return [
            'available' => empty($allConflicts),
            'conflicts' => $allConflicts,
        ];
    }

    /**
     * Toggle template active/inactive
     */
    public function toggleActive(int $templateId, int $instanceId): bool
    {
        $this->db->where('id', $templateId);
        $this->db->where('instances_id', $instanceId);
        $t = $this->db->getOne('recurring_project_templates', ['active']);
        if (!$t) return false;
        $this->db->where('id', $templateId);
        return $this->db->update('recurring_project_templates', ['active' => $t['active'] ? 0 : 1]);
    }

    /**
     * Delete template
     */
    public function deleteTemplate(int $templateId, int $instanceId): bool
    {
        $this->db->where('id', $templateId);
        $this->db->where('instances_id', $instanceId);
        return $this->db->update('recurring_project_templates', ['deleted' => 1]);
    }
}
