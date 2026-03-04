<?php
/**
 * Rate-Limiting Service
 *
 * IP-basiertes Rate-Limiting ueber die Datenbank.
 * Schuetzt Login, Partner-Code-Generierung und andere sensitive Endpoints.
 */
class RateLimitService
{
    private $db;

    // Standard-Limits
    private const LIMITS = [
        'login' => ['max_attempts' => 5, 'window_minutes' => 15, 'lockout_minutes' => 30],
        'partner_code' => ['max_attempts' => 5, 'window_minutes' => 60, 'lockout_minutes' => 60],
        'password_reset' => ['max_attempts' => 3, 'window_minutes' => 60, 'lockout_minutes' => 60],
        'api_general' => ['max_attempts' => 100, 'window_minutes' => 1, 'lockout_minutes' => 5],
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Prueft ob eine Aktion erlaubt ist (noch nicht rate-limited)
     */
    public function isAllowed(string $action, string $identifier): bool
    {
        $limit = self::LIMITS[$action] ?? self::LIMITS['api_general'];
        $windowStart = date('Y-m-d H:i:s', strtotime("-{$limit['window_minutes']} minutes"));

        $this->db->where('action', $action);
        $this->db->where('identifier', $identifier);
        $this->db->where('attempted_at >= ?', [$windowStart]);
        $count = $this->db->getValue('rate_limits', 'count(*)');

        return ($count < $limit['max_attempts']);
    }

    /**
     * Protokolliert einen Versuch
     */
    public function recordAttempt(string $action, string $identifier, bool $success = false): void
    {
        $this->db->insert('rate_limits', [
            'action' => $action,
            'identifier' => $identifier,
            'success' => $success ? 1 : 0,
            'ip_address' => self::getClientIp(),
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Gibt verbleibende Versuche zurueck
     */
    public function remainingAttempts(string $action, string $identifier): int
    {
        $limit = self::LIMITS[$action] ?? self::LIMITS['api_general'];
        $windowStart = date('Y-m-d H:i:s', strtotime("-{$limit['window_minutes']} minutes"));

        $this->db->where('action', $action);
        $this->db->where('identifier', $identifier);
        $this->db->where('attempted_at >= ?', [$windowStart]);
        $count = (int)$this->db->getValue('rate_limits', 'count(*)');

        return max(0, $limit['max_attempts'] - $count);
    }

    /**
     * Setzt erfolgreiche Authentifizierung (loescht fehlgeschlagene Versuche)
     */
    public function resetOnSuccess(string $action, string $identifier): void
    {
        $this->db->where('action', $action);
        $this->db->where('identifier', $identifier);
        $this->db->where('success', 0);
        $this->db->delete('rate_limits');
    }

    /**
     * Bereinigt alte Eintraege (als Cronjob aufrufbar)
     */
    public function cleanup(int $olderThanHours = 24): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$olderThanHours} hours"));
        $this->db->where('attempted_at < ?', [$cutoff]);
        return $this->db->delete('rate_limits') ? $this->db->count : 0;
    }

    private static function getClientIp(): ?string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP) ?: null;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return filter_var(trim($ips[0]), FILTER_VALIDATE_IP) ?: null;
        }
        return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
    }
}
