<?php
/**
 * Transportplanung (Transport Planning)
 *
 * Verwaltet Transportplaene fuer Equipment-Lieferungen und -Abholungen.
 */
class TransportService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Neuen Transportplan erstellen
     */
    public function createPlan(int $instanceId, array $data): ?int
    {
        $insert = [
            'instances_id' => $instanceId,
            'projects_id' => $data['projects_id'] ?? null,
            'from_warehouse_id' => $data['from_warehouse_id'] ?? null,
            'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
            'to_address' => $data['to_address'] ?? null,
            'driver_name' => $data['driver_name'] ?? null,
            'vehicle' => $data['vehicle'] ?? null,
            'planned_date' => $data['planned_date'],
            'planned_time' => $data['planned_time'] ?? null,
            'status' => 'planned',
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('transport_plans', $insert) ?: null;
    }

    /**
     * Transportplan aktualisieren
     */
    public function updatePlan(int $planId, array $data): bool
    {
        $update = [];
        $allowed = ['projects_id', 'from_warehouse_id', 'to_warehouse_id', 'to_address',
                     'driver_name', 'vehicle', 'planned_date', 'planned_time', 'status', 'notes'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) return false;

        $this->db->where('id', $planId);
        return $this->db->update('transport_plans', $update);
    }

    /**
     * Position zum Transportplan hinzufuegen
     */
    public function addItem(int $planId, int $assetTypeId, int $quantity): ?int
    {
        return $this->db->insert('transport_items', [
            'transport_plan_id' => $planId,
            'assetTypes_id' => $assetTypeId,
            'quantity' => $quantity,
            'loaded' => 0,
            'delivered' => 0,
        ]) ?: null;
    }

    /**
     * Position als geladen markieren
     */
    public function markLoaded(int $itemId): bool
    {
        $this->db->where('id', $itemId);
        return $this->db->update('transport_items', ['loaded' => 1]);
    }

    /**
     * Position als geliefert markieren
     */
    public function markDelivered(int $itemId): bool
    {
        $this->db->where('id', $itemId);
        return $this->db->update('transport_items', ['delivered' => 1]);
    }

    /**
     * Alle Transportplaene fuer ein Projekt
     */
    public function getPlansForProject(int $projectId): array
    {
        $sql = "SELECT tp.*,
                       wf.name AS from_warehouse_name,
                       wt.name AS to_warehouse_name,
                       p.projects_name
                FROM transport_plans tp
                LEFT JOIN warehouses wf ON tp.from_warehouse_id = wf.id
                LEFT JOIN warehouses wt ON tp.to_warehouse_id = wt.id
                LEFT JOIN projects p ON tp.projects_id = p.projects_id
                WHERE tp.projects_id = ?
                ORDER BY tp.planned_date ASC, tp.planned_time ASC";
        $plans = $this->db->rawQuery($sql, [$projectId]) ?: [];
        return $this->enrichPlansWithItems($plans);
    }

    /**
     * Alle Transportplaene fuer einen Tag
     */
    public function getPlansForDate(int $instanceId, string $date): array
    {
        $sql = "SELECT tp.*,
                       wf.name AS from_warehouse_name,
                       wt.name AS to_warehouse_name,
                       p.projects_name
                FROM transport_plans tp
                LEFT JOIN warehouses wf ON tp.from_warehouse_id = wf.id
                LEFT JOIN warehouses wt ON tp.to_warehouse_id = wt.id
                LEFT JOIN projects p ON tp.projects_id = p.projects_id
                WHERE tp.instances_id = ? AND tp.planned_date = ?
                ORDER BY tp.planned_time ASC";
        $plans = $this->db->rawQuery($sql, [$instanceId, $date]) ?: [];
        return $this->enrichPlansWithItems($plans);
    }

    /**
     * Alle Transportplaene einer Instanz (optional mit Datumsbereich)
     */
    public function getPlans(int $instanceId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT tp.*,
                       wf.name AS from_warehouse_name,
                       wt.name AS to_warehouse_name,
                       p.projects_name
                FROM transport_plans tp
                LEFT JOIN warehouses wf ON tp.from_warehouse_id = wf.id
                LEFT JOIN warehouses wt ON tp.to_warehouse_id = wt.id
                LEFT JOIN projects p ON tp.projects_id = p.projects_id
                WHERE tp.instances_id = ?";
        $params = [$instanceId];

        if ($dateFrom) {
            $sql .= " AND tp.planned_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND tp.planned_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY tp.planned_date ASC, tp.planned_time ASC";
        $plans = $this->db->rawQuery($sql, $params) ?: [];
        return $this->enrichPlansWithItems($plans);
    }

    /**
     * Transportplan stornieren
     */
    public function cancelPlan(int $planId): bool
    {
        $this->db->where('id', $planId);
        return $this->db->update('transport_plans', ['status' => 'cancelled']);
    }

    /**
     * Einzelnen Plan laden
     */
    public function getPlan(int $planId, int $instanceId): ?array
    {
        $sql = "SELECT tp.*,
                       wf.name AS from_warehouse_name,
                       wt.name AS to_warehouse_name,
                       p.projects_name
                FROM transport_plans tp
                LEFT JOIN warehouses wf ON tp.from_warehouse_id = wf.id
                LEFT JOIN warehouses wt ON tp.to_warehouse_id = wt.id
                LEFT JOIN projects p ON tp.projects_id = p.projects_id
                WHERE tp.id = ? AND tp.instances_id = ?";
        $plan = $this->db->rawQuery($sql, [$planId, $instanceId]);
        if (!$plan || count($plan) === 0) return null;

        $plan = $plan[0];
        $plan['items'] = $this->getItemsForPlan($planId);
        return $plan;
    }

    /**
     * Positionen eines Plans laden
     */
    private function getItemsForPlan(int $planId): array
    {
        $sql = "SELECT ti.*, at.assetTypes_name
                FROM transport_items ti
                JOIN assetTypes at ON ti.assetTypes_id = at.assetTypes_id
                WHERE ti.transport_plan_id = ?
                ORDER BY at.assetTypes_name ASC";
        return $this->db->rawQuery($sql, [$planId]) ?: [];
    }

    /**
     * Plaene mit Positionen anreichern
     */
    private function enrichPlansWithItems(array $plans): array
    {
        foreach ($plans as &$plan) {
            $plan['items'] = $this->getItemsForPlan($plan['id']);
        }
        return $plans;
    }
}
