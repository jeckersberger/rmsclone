<?php
/**
 * ProjectNotificationService - Automatische Projekt-Benachrichtigungen
 *
 * Sendet automatische E-Mails:
 * - Projektbestaetigung an Kunden
 * - Termin-Erinnerung X Tage vor Start
 * - Feedback-Anfrage nach Projekt-Ende
 */
class ProjectNotificationService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Bestaetigugs-E-Mail senden
     */
    public function sendConfirmation(int $projectId, int $instanceId): array
    {
        $project = $this->getProjectWithClient($projectId);
        if (!$project) return ['success' => false, 'error' => 'Projekt nicht gefunden'];
        if (empty($project['clients_email'])) return ['success' => false, 'error' => 'Keine E-Mail-Adresse vorhanden'];

        $templateService = new EmailTemplateService($this->db);
        $rendered = $templateService->render($instanceId, EmailTemplateService::TYPE_PROJECT_CONFIRMATION, [
            'kunde' => $project['clients_name'] ?? '',
            'projekt' => $project['projects_name'] ?? '',
            'startdatum' => date('d.m.Y', strtotime($project['projects_dates_use_start'] ?? '')),
            'enddatum' => date('d.m.Y', strtotime($project['projects_dates_use_end'] ?? '')),
            'gesamtpreis' => number_format(floatval($project['projects_value_total'] ?? 0), 2, ',', '.'),
            'firmenname' => $project['instances_name'] ?? '',
            'datum' => date('d.m.Y'),
        ]);

        if (!$rendered) return ['success' => false, 'error' => 'Template nicht gefunden'];

        return $this->sendEmail($project['clients_email'], $rendered['subject'], $rendered['body'], $instanceId);
    }

    /**
     * Projekte finden, die in X Tagen starten (fuer Cron)
     */
    public function getProjectsDueForReminder(int $daysBeforeStart = 3): array
    {
        $targetDate = date('Y-m-d', strtotime("+{$daysBeforeStart} days"));
        $sql = "SELECT p.projects_id, p.projects_name, p.projects_dates_use_start,
                       p.projects_dates_use_end, p.instances_id,
                       c.clients_name, c.clients_email,
                       i.instances_name
                FROM projects p
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                LEFT JOIN instances i ON p.instances_id = i.instances_id
                WHERE DATE(p.projects_dates_use_start) = ?
                AND p.projects_deleted = 0
                AND p.projects_reminder_sent = 0
                AND c.clients_email IS NOT NULL
                AND c.clients_email != ''";
        return $this->db->rawQuery($sql, [$targetDate]) ?: [];
    }

    /**
     * Projekte finden, die vor X Tagen endeten (fuer Feedback-Cron)
     */
    public function getProjectsDueForFeedback(int $daysAfterEnd = 2): array
    {
        $targetDate = date('Y-m-d', strtotime("-{$daysAfterEnd} days"));
        $sql = "SELECT p.projects_id, p.projects_name, p.projects_dates_use_end,
                       p.instances_id,
                       c.clients_name, c.clients_email,
                       i.instances_name
                FROM projects p
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                LEFT JOIN instances i ON p.instances_id = i.instances_id
                WHERE DATE(p.projects_dates_use_end) = ?
                AND p.projects_deleted = 0
                AND p.projects_feedback_sent = 0
                AND c.clients_email IS NOT NULL
                AND c.clients_email != ''";
        return $this->db->rawQuery($sql, [$targetDate]) ?: [];
    }

    /**
     * Erinnerungen versenden (Cron)
     */
    public function sendReminders(int $daysBeforeStart = 3): array
    {
        $projects = $this->getProjectsDueForReminder($daysBeforeStart);
        $sent = 0;
        $errors = [];

        foreach ($projects as $project) {
            $templateService = new EmailTemplateService($this->db);
            $rendered = $templateService->render($project['instances_id'], EmailTemplateService::TYPE_PROJECT_REMINDER, [
                'kunde' => $project['clients_name'],
                'projekt' => $project['projects_name'],
                'startdatum' => date('d.m.Y', strtotime($project['projects_dates_use_start'])),
                'tage_bis_start' => $daysBeforeStart,
                'firmenname' => $project['instances_name'] ?? '',
                'datum' => date('d.m.Y'),
            ]);

            if ($rendered) {
                $result = $this->sendEmail($project['clients_email'], $rendered['subject'], $rendered['body'], $project['instances_id']);
                if ($result['success']) {
                    $this->db->where('projects_id', $project['projects_id']);
                    $this->db->update('projects', ['projects_reminder_sent' => 1]);
                    $sent++;
                } else {
                    $errors[] = $project['projects_id'] . ': ' . ($result['error'] ?? 'Unbekannt');
                }
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Feedback-Anfragen versenden (Cron)
     */
    public function sendFeedbackRequests(int $daysAfterEnd = 2): array
    {
        $projects = $this->getProjectsDueForFeedback($daysAfterEnd);
        $sent = 0;
        $errors = [];

        foreach ($projects as $project) {
            $templateService = new EmailTemplateService($this->db);
            $rendered = $templateService->render($project['instances_id'], EmailTemplateService::TYPE_FEEDBACK_REQUEST, [
                'kunde' => $project['clients_name'],
                'projekt' => $project['projects_name'],
                'enddatum' => date('d.m.Y', strtotime($project['projects_dates_use_end'])),
                'firmenname' => $project['instances_name'] ?? '',
                'datum' => date('d.m.Y'),
            ]);

            if ($rendered) {
                $result = $this->sendEmail($project['clients_email'], $rendered['subject'], $rendered['body'], $project['instances_id']);
                if ($result['success']) {
                    $this->db->where('projects_id', $project['projects_id']);
                    $this->db->update('projects', ['projects_feedback_sent' => 1]);
                    $sent++;
                } else {
                    $errors[] = $project['projects_id'] . ': ' . ($result['error'] ?? 'Unbekannt');
                }
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    private function getProjectWithClient(int $projectId): ?array
    {
        $sql = "SELECT p.*, c.clients_name, c.clients_email, i.instances_name
                FROM projects p
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                LEFT JOIN instances i ON p.instances_id = i.instances_id
                WHERE p.projects_id = ? AND p.projects_deleted = 0";
        $result = $this->db->rawQuery($sql, [$projectId]);
        return $result ? $result[0] : null;
    }

    private function sendEmail(string $to, string $subject, string $body, int $instanceId): array
    {
        // Integration mit bestehendem E-Mail-System (notify-Funktion)
        if (function_exists('notify')) {
            try {
                notify($to, $subject, $body);
                return ['success' => true];
            } catch (Exception $e) {
                error_log("[ProjectNotification] E-Mail-Fehler: " . $e->getMessage());
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // Fallback: mail()
        $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
        $sent = mail($to, $subject, $body, $headers);
        return ['success' => $sent, 'error' => $sent ? null : 'mail() fehlgeschlagen'];
    }
}
