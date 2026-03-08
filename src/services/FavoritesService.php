<?php
/**
 * FavoritesService - Benutzer-Lesezeichen/Favoriten
 *
 * Ermoeglicht das Speichern und Verwalten von Favoriten-Links
 * fuer schnellen Zugriff auf haeufig genutzte Seiten.
 */
class FavoritesService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Favorit hinzufuegen
     */
    public function add(int $userId, int $instanceId, string $title, string $url, ?string $icon = null): int
    {
        // Max sort_order ermitteln
        $this->db->where('users_userid', $userId);
        $this->db->where('instances_id', $instanceId);
        $maxOrder = (int) $this->db->getValue('user_favorites', 'COALESCE(MAX(sort_order), 0)');

        $this->db->insert('user_favorites', [
            'users_userid' => $userId,
            'instances_id' => $instanceId,
            'title' => $title,
            'url' => $url,
            'icon' => $icon ?? 'fa-star',
            'sort_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->getInsertId();
    }

    /**
     * Favoriten eines Benutzers abrufen
     */
    public function getAll(int $userId, int $instanceId): array
    {
        $this->db->where('users_userid', $userId);
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('sort_order', 'ASC');
        return $this->db->get('user_favorites') ?: [];
    }

    /**
     * Favorit entfernen
     */
    public function remove(int $favoriteId, int $userId): bool
    {
        $this->db->where('id', $favoriteId);
        $this->db->where('users_userid', $userId);
        return $this->db->delete('user_favorites');
    }

    /**
     * Reihenfolge aktualisieren
     */
    public function reorder(int $userId, array $orderedIds): bool
    {
        foreach ($orderedIds as $order => $id) {
            $this->db->where('id', intval($id));
            $this->db->where('users_userid', $userId);
            $this->db->update('user_favorites', ['sort_order' => $order]);
        }
        return true;
    }

    /**
     * Prueft ob eine URL bereits als Favorit gespeichert ist
     */
    public function isFavorite(int $userId, int $instanceId, string $url): bool
    {
        $this->db->where('users_userid', $userId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('url', $url);
        return (bool) $this->db->getOne('user_favorites');
    }
}
