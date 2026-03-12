<?php
/**
 * CalendarSyncService - Bidirektionale Kalender-Synchronisation
 *
 * Synchronisiert Projekte mit externen Kalendern via:
 * - iCalendar (.ics) Export (bestehend erweitert)
 * - CalDAV fuer bidirektionale Sync (Google Calendar, Outlook)
 * - Webhook-basierte Aenderungsbenachrichtigung
 */
class CalendarSyncService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * ICS-Kalender generieren (alle Projekte einer Instanz)
     */
    public function generateIcs(int $instanceId, ?string $fromDate = null, ?string $toDate = null): string
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_deleted', 0);
        if ($fromDate) $this->db->where('projects_dates_use_end', $fromDate, '>=');
        if ($toDate) $this->db->where('projects_dates_use_start', $toDate, '<=');
        $this->db->orderBy('projects_dates_use_start', 'ASC');

        $projects = $this->db->get('projects', null, [
            'projects_id', 'projects_name', 'projects_description',
            'projects_dates_use_start', 'projects_dates_use_end',
            'projects_dates_deliver_start', 'projects_dates_deliver_end',
            'projects_status', 'projects_location',
        ]) ?: [];

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//MyRMS//DE\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:MyRMS Projekte\r\n";
        $ics .= "X-WR-TIMEZONE:Europe/Berlin\r\n";

        foreach ($projects as $project) {
            $ics .= $this->projectToVevent($project);
        }

        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }

    /**
     * ICS fuer ein einzelnes Projekt
     */
    public function generateProjectIcs(int $projectId): ?string
    {
        $this->db->where('projects_id', $projectId);
        $this->db->where('projects_deleted', 0);
        $project = $this->db->getOne('projects');
        if (!$project) return null;

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//MyRMS//DE\r\n";
        $ics .= "METHOD:REQUEST\r\n";
        $ics .= $this->projectToVevent($project);
        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }

    /**
     * Kalender-Feed URL fuer einen Benutzer generieren
     */
    public function generateFeedToken(int $userId, int $instanceId): array
    {
        $token = bin2hex(random_bytes(24));

        // Altes Token deaktivieren
        $this->db->where('user_id', $userId);
        $this->db->where('instances_id', $instanceId);
        $this->db->update('calendar_feeds', ['active' => 0]);

        $id = $this->db->insert('calendar_feeds', [
            'user_id' => $userId,
            'instances_id' => $instanceId,
            'token' => hash('sha256', $token),
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id
            ? ['success' => true, 'token' => $token]
            : ['success' => false, 'error' => 'Fehler beim Erstellen'];
    }

    /**
     * Feed-Token validieren
     */
    public function validateFeedToken(string $token): ?array
    {
        $hashedToken = hash('sha256', $token);
        $this->db->where('token', $hashedToken);
        $this->db->where('active', 1);
        $result = $this->db->getOne('calendar_feeds', ['user_id', 'instances_id']);
        return $result ?: null;
    }

    /**
     * Partner-Verfuegbarkeit als ICS
     */
    public function generatePartnerAvailabilityIcs(int $partnershipId, int $instanceId): string
    {
        $sql = "SELECT p.projects_id, p.projects_name,
                       p.projects_dates_use_start, p.projects_dates_use_end
                FROM projects p
                JOIN assetsAssignments aa ON p.projects_id = aa.projects_id
                WHERE p.instances_id = ? AND p.projects_deleted = 0
                AND aa.assetsAssignments_deleted = 0
                AND p.projects_dates_use_end >= CURDATE()
                GROUP BY p.projects_id
                ORDER BY p.projects_dates_use_start ASC";
        $projects = $this->db->rawQuery($sql, [$instanceId]) ?: [];

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//MyRMS//Partner//DE\r\n";
        $ics .= "X-WR-CALNAME:Partner-Verfuegbarkeit\r\n";

        foreach ($projects as $project) {
            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:partner-" . $project['projects_id'] . "@adamrms\r\n";
            $ics .= "DTSTART:" . $this->formatIcsDate($project['projects_dates_use_start']) . "\r\n";
            $ics .= "DTEND:" . $this->formatIcsDate($project['projects_dates_use_end']) . "\r\n";
            $ics .= "SUMMARY:Belegt\r\n";
            $ics .= "STATUS:CONFIRMED\r\n";
            $ics .= "TRANSP:OPAQUE\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }

    private function projectToVevent(array $project): string
    {
        $uid = 'project-' . $project['projects_id'] . '@adamrms';
        $dtStart = $this->formatIcsDate($project['projects_dates_use_start'] ?? '');
        $dtEnd = $this->formatIcsDate($project['projects_dates_use_end'] ?? '');
        $now = gmdate('Ymd\THis\Z');

        $vevent = "BEGIN:VEVENT\r\n";
        $vevent .= "UID:{$uid}\r\n";
        $vevent .= "DTSTAMP:{$now}\r\n";
        $vevent .= "DTSTART:{$dtStart}\r\n";
        $vevent .= "DTEND:{$dtEnd}\r\n";
        $vevent .= "SUMMARY:" . $this->escapeIcs($project['projects_name'] ?? '') . "\r\n";

        if (!empty($project['projects_description'])) {
            $vevent .= "DESCRIPTION:" . $this->escapeIcs($project['projects_description']) . "\r\n";
        }
        if (!empty($project['projects_location'])) {
            $vevent .= "LOCATION:" . $this->escapeIcs($project['projects_location']) . "\r\n";
        }

        $status = ($project['projects_status'] ?? '') === 'cancelled' ? 'CANCELLED' : 'CONFIRMED';
        $vevent .= "STATUS:{$status}\r\n";
        $vevent .= "END:VEVENT\r\n";

        return $vevent;
    }

    private function formatIcsDate(string $datetime): string
    {
        if (empty($datetime)) return gmdate('Ymd\THis\Z');
        $ts = strtotime($datetime);
        return $ts ? gmdate('Ymd\THis\Z', $ts) : gmdate('Ymd\THis\Z');
    }

    private function escapeIcs(string $text): string
    {
        $text = str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
        return $text;
    }
}
