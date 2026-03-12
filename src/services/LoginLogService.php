<?php
/**
 * LoginLogService - Login-Protokoll / Audit-Log
 *
 * Protokolliert Login-Versuche (erfolgreich und fehlgeschlagen)
 * und stellt Abfragen fuer Benutzer- und Instanz-Ansicht bereit.
 */
class LoginLogService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Protokolliert einen Login-Versuch
     */
    public function logAttempt(?int $userId, string $ip, ?string $userAgent, bool $success, ?string $reason = null): bool
    {
        $data = [
            'users_userid' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'success' => $success ? 1 : 0,
            'failure_reason' => $reason,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return (bool) $this->db->insert('login_log', $data);
    }

    /**
     * Login-Verlauf fuer einen bestimmten Benutzer
     */
    public function getLog(int $userId, int $limit = 50): ?array
    {
        $this->db->where('users_userid', $userId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('login_log', $limit);
    }

    /**
     * Fehlgeschlagene Versuche in den letzten X Minuten
     */
    public function getRecentFailures(int $userId, int $minutes = 30): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        $this->db->where('users_userid', $userId);
        $this->db->where('success', 0);
        $this->db->where('created_at', $since, '>=');
        return (int) $this->db->getValue('login_log', 'count(*)');
    }

    /**
     * Alle Logins fuer eine Instanz (Admin-Ansicht)
     * Verknuepft ueber userInstances, um nur Benutzer der Instanz anzuzeigen.
     */
    public function getLogByInstance(int $instanceId, int $limit = 100): ?array
    {
        $this->db->join('users', 'login_log.users_userid = users.users_userid', 'LEFT');
        $this->db->join('userInstances', 'users.users_userid = userInstances.users_userid', 'LEFT');
        $this->db->join('instancePositions', 'userInstances.instancePositions_id = instancePositions.instancePositions_id', 'LEFT');
        $this->db->where('instancePositions.instances_id', $instanceId);
        $this->db->where('userInstances.userInstances_deleted', 0);
        $this->db->orderBy('login_log.created_at', 'DESC');
        $this->db->groupBy('login_log.id');
        return $this->db->get('login_log', $limit, [
            'login_log.*',
            'users.users_name1',
            'users.users_name2',
            'users.users_email',
        ]);
    }

    /**
     * Ermittelt die Client-IP-Adresse
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP) ?: '0.0.0.0';
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return filter_var(trim($ips[0]), FILTER_VALIDATE_IP) ?: '0.0.0.0';
        }
        return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }
}
