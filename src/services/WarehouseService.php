<?php
/**
 * Lagerverwaltung (Warehouse Management)
 *
 * Verwaltet Lagerstandorte, Bestandszuordnungen und Umlagerungen.
 */
class WarehouseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Neues Lager anlegen
     */
    public function createWarehouse(int $instanceId, array $data): ?int
    {
        $insert = [
            'instances_id' => $instanceId,
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Wenn als Standard markiert, alle anderen zuruecksetzen
        if ($insert['is_default']) {
            $this->db->where('instances_id', $instanceId);
            $this->db->update('warehouses', ['is_default' => 0]);
        }

        return $this->db->insert('warehouses', $insert) ?: null;
    }

    /**
     * Alle Lager einer Instanz auflisten
     */
    public function getWarehouses(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('is_default', 'DESC');
        $this->db->orderBy('name', 'ASC');
        return $this->db->get('warehouses') ?: [];
    }

    /**
     * Einzelnes Lager laden
     */
    public function getWarehouse(int $warehouseId, int $instanceId): ?array
    {
        $this->db->where('id', $warehouseId);
        $this->db->where('instances_id', $instanceId);
        $result = $this->db->getOne('warehouses');
        return $result ?: null;
    }

    /**
     * Equipment einem Lager zuweisen (Bestand setzen)
     */
    public function assignAsset(int $assetTypeId, int $warehouseId, int $quantity): bool
    {
        // Pruefen ob Zuordnung existiert
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('warehouse_id', $warehouseId);
        $existing = $this->db->getOne('asset_warehouse_assignments');

        if ($existing) {
            $this->db->where('id', $existing['id']);
            return $this->db->update('asset_warehouse_assignments', ['quantity' => $quantity]);
        } else {
            return (bool) $this->db->insert('asset_warehouse_assignments', [
                'assetTypes_id' => $assetTypeId,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
            ]);
        }
    }

    /**
     * Bestand zwischen Lagern umlagern
     */
    public function transferAsset(int $assetTypeId, int $fromWarehouseId, int $toWarehouseId, int $quantity): bool
    {
        // Quellbestand pruefen
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('warehouse_id', $fromWarehouseId);
        $source = $this->db->getOne('asset_warehouse_assignments');

        if (!$source || $source['quantity'] < $quantity) {
            return false; // Nicht genug Bestand
        }

        // Quellbestand reduzieren
        $newSourceQty = $source['quantity'] - $quantity;
        $this->db->where('id', $source['id']);
        if ($newSourceQty > 0) {
            $this->db->update('asset_warehouse_assignments', ['quantity' => $newSourceQty]);
        } else {
            $this->db->delete('asset_warehouse_assignments');
        }

        // Zielbestand erhoehen
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('warehouse_id', $toWarehouseId);
        $target = $this->db->getOne('asset_warehouse_assignments');

        if ($target) {
            $this->db->where('id', $target['id']);
            $this->db->update('asset_warehouse_assignments', ['quantity' => $target['quantity'] + $quantity]);
        } else {
            $this->db->insert('asset_warehouse_assignments', [
                'assetTypes_id' => $assetTypeId,
                'warehouse_id' => $toWarehouseId,
                'quantity' => $quantity,
            ]);
        }

        return true;
    }

    /**
     * Alle Bestaende eines Lagers auflisten
     */
    public function getStockByWarehouse(int $warehouseId): array
    {
        $sql = "SELECT awa.*, at.assetTypes_name, at.assetTypes_id,
                       ac.assetCategories_name, m.manufacturers_name
                FROM asset_warehouse_assignments awa
                JOIN assetTypes at ON awa.assetTypes_id = at.assetTypes_id
                LEFT JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                LEFT JOIN manufacturers m ON at.manufacturers_id = m.manufacturers_id
                WHERE awa.warehouse_id = ?
                ORDER BY ac.assetCategories_name ASC, at.assetTypes_name ASC";
        return $this->db->rawQuery($sql, [$warehouseId]) ?: [];
    }

    /**
     * Wo ist ein bestimmter Equipment-Typ gelagert?
     */
    public function getAssetLocations(int $assetTypeId): array
    {
        $sql = "SELECT awa.*, w.name AS warehouse_name, w.address AS warehouse_address
                FROM asset_warehouse_assignments awa
                JOIN warehouses w ON awa.warehouse_id = w.id
                WHERE awa.assetTypes_id = ?
                ORDER BY w.name ASC";
        return $this->db->rawQuery($sql, [$assetTypeId]) ?: [];
    }
}
