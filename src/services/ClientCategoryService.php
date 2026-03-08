<?php
/**
 * Kundenkategorien / Tags
 *
 * Verwaltung von Kategorien pro Instanz sowie Zuordnung zu Kunden.
 * Kategorien koennen farblich gekennzeichnet werden (Badges).
 */
class ClientCategoryService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ──────────────── Kategorien-CRUD ────────────────

    /**
     * Alle Kategorien einer Instanz laden
     */
    public function getCategories(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('name', 'ASC');
        return $this->db->get('client_categories') ?: [];
    }

    /**
     * Einzelne Kategorie laden
     */
    public function getCategory(int $categoryId): ?array
    {
        $this->db->where('id', $categoryId);
        $cat = $this->db->getOne('client_categories');
        return $cat ?: null;
    }

    /**
     * Neue Kategorie anlegen
     */
    public function createCategory(int $instanceId, string $name, string $color = '#6c757d'): ?int
    {
        $id = $this->db->insert('client_categories', [
            'instances_id' => $instanceId,
            'name'         => $name,
            'color'        => $color,
        ]);
        return $id ?: null;
    }

    /**
     * Kategorie aktualisieren
     */
    public function updateCategory(int $categoryId, int $instanceId, array $data): bool
    {
        $update = [];
        if (isset($data['name']))  $update['name']  = $data['name'];
        if (isset($data['color'])) $update['color'] = $data['color'];
        if (empty($update)) return false;

        $this->db->where('id', $categoryId);
        $this->db->where('instances_id', $instanceId);
        return (bool)$this->db->update('client_categories', $update);
    }

    /**
     * Kategorie loeschen (Zuordnungen werden per FK CASCADE entfernt)
     */
    public function deleteCategory(int $categoryId, int $instanceId): bool
    {
        $this->db->where('id', $categoryId);
        $this->db->where('instances_id', $instanceId);
        return (bool)$this->db->delete('client_categories');
    }

    // ──────────────── Zuordnung Kategorie <-> Kunde ────────────────

    /**
     * Kategorie einem Kunden zuweisen
     */
    public function assignCategory(int $clientId, int $categoryId): bool
    {
        // Pruefen ob Zuordnung bereits existiert
        $this->db->where('clients_id', $clientId);
        $this->db->where('category_id', $categoryId);
        $existing = $this->db->getOne('client_category_assignments');
        if ($existing) return true; // bereits zugewiesen

        $id = $this->db->insert('client_category_assignments', [
            'clients_id'  => $clientId,
            'category_id' => $categoryId,
        ]);
        return (bool)$id;
    }

    /**
     * Kategorie-Zuordnung entfernen
     */
    public function removeCategory(int $clientId, int $categoryId): bool
    {
        $this->db->where('clients_id', $clientId);
        $this->db->where('category_id', $categoryId);
        return (bool)$this->db->delete('client_category_assignments');
    }

    /**
     * Alle Kategorien eines Kunden laden (inkl. Farbe und Name)
     */
    public function getClientCategories(int $clientId): array
    {
        $this->db->join('client_categories cc', 'cca.category_id = cc.id', 'INNER');
        $this->db->where('cca.clients_id', $clientId);
        $this->db->orderBy('cc.name', 'ASC');
        return $this->db->get('client_category_assignments cca', null, [
            'cc.id', 'cc.name', 'cc.color'
        ]) ?: [];
    }

    /**
     * Alle Kunden einer Kategorie laden
     */
    public function getClientsByCategory(int $categoryId): array
    {
        $this->db->join('clients c', 'cca.clients_id = c.clients_id', 'INNER');
        $this->db->where('cca.category_id', $categoryId);
        $this->db->where('c.clients_deleted', 0);
        $this->db->orderBy('c.clients_name', 'ASC');
        return $this->db->get('client_category_assignments cca', null, [
            'c.clients_id', 'c.clients_name', 'c.clients_email', 'c.clients_phone'
        ]) ?: [];
    }
}
