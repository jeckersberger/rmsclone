<?php
/**
 * NotificationService - In-App Benachrichtigungen
 *
 * Erstellt, liest und verwaltet In-App-Benachrichtigungen.
 * Ergaenzt das bestehende E-Mail-Benachrichtigungssystem.
 */
class NotificationService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Neue Benachrichtigung erstellen
     */
    public function create(int $userId, ?int $instanceId, string $type, string $title, ?string $message = null, ?string $link = null, ?string $icon = null): int
    {
        $this->db->insert('notifications', [
            'users_userid' => $userId,
            'instances_id' => $instanceId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'icon' => $icon ?? $this->getDefaultIcon($type),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->getInsertId();
    }

    /**
     * Benachrichtigung an mehrere Benutzer senden
     */
    public function broadcast(array $userIds, ?int $instanceId, string $type, string $title, ?string $message = null, ?string $link = null): void
    {
        foreach ($userIds as $userId) {
            $this->create($userId, $instanceId, $type, $title, $message, $link);
        }
    }

    /**
     * Ungelesene Benachrichtigungen eines Benutzers abrufen
     */
    public function getUnread(int $userId, ?int $instanceId = null, int $limit = 20): array
    {
        $this->db->where('users_userid', $userId);
        $this->db->where('read_at IS NULL');
        if ($instanceId) {
            $this->db->where('(instances_id = ? OR instances_id IS NULL)', [$instanceId]);
        }
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('notifications', $limit) ?: [];
    }

    /**
     * Alle Benachrichtigungen (mit Pagination)
     */
    public function getAll(int $userId, ?int $instanceId = null, int $limit = 50, int $offset = 0): array
    {
        $this->db->where('users_userid', $userId);
        if ($instanceId) {
            $this->db->where('(instances_id = ? OR instances_id IS NULL)', [$instanceId]);
        }
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('notifications', [$offset, $limit]) ?: [];
    }

    /**
     * Anzahl ungelesener Benachrichtigungen
     */
    public function getUnreadCount(int $userId, ?int $instanceId = null): int
    {
        $this->db->where('users_userid', $userId);
        $this->db->where('read_at IS NULL');
        if ($instanceId) {
            $this->db->where('(instances_id = ? OR instances_id IS NULL)', [$instanceId]);
        }
        return (int) $this->db->getValue('notifications', 'count(*)');
    }

    /**
     * Einzelne Benachrichtigung als gelesen markieren
     */
    public function markRead(int $notificationId, int $userId): bool
    {
        $this->db->where('id', $notificationId);
        $this->db->where('users_userid', $userId);
        return $this->db->update('notifications', ['read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Alle Benachrichtigungen als gelesen markieren
     */
    public function markAllRead(int $userId, ?int $instanceId = null): bool
    {
        $this->db->where('users_userid', $userId);
        $this->db->where('read_at IS NULL');
        if ($instanceId) {
            $this->db->where('(instances_id = ? OR instances_id IS NULL)', [$instanceId]);
        }
        return $this->db->update('notifications', ['read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Alte Benachrichtigungen loeschen (> 90 Tage)
     */
    public function cleanup(int $daysOld = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        $this->db->where('created_at', $cutoff, '<');
        $this->db->where('read_at IS NOT NULL');
        return $this->db->delete('notifications') ? $this->db->count : 0;
    }

    /**
     * Standard-Icon pro Benachrichtigungstyp
     */
    private function getDefaultIcon(string $type): string
    {
        $icons = [
            'project' => 'fa-project-diagram',
            'invoice' => 'fa-file-invoice',
            'payment' => 'fa-money-bill',
            'dunning' => 'fa-exclamation-triangle',
            'equipment' => 'fa-tools',
            'partner' => 'fa-handshake',
            'system' => 'fa-cog',
            'crew' => 'fa-users',
            'maintenance' => 'fa-wrench',
        ];
        return $icons[$type] ?? 'fa-bell';
    }
}
