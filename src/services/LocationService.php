<?php

/**
 * Location Tracking Service
 *
 * Manages location definitions and tracks current location of assets and stock instances.
 * Supports both predefined locations (with icons, colors) and custom text locations.
 */
class LocationService
{
    private $db;

    public function __construct($db = null)
    {
        global $DBLIB;
        $this->db = $db ?: $DBLIB;
    }

    /**
     * List all locations, optionally filtered to active only
     *
     * @param bool $activeOnly If true, only return is_active=1 locations
     * @return array List of locations ordered by sort_order
     */
    public function listLocations(bool $activeOnly = true): array
    {
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }
        $this->db->orderBy('sort_order', 'ASC');
        $locations = $this->db->get('locations');
        return $locations ?: [];
    }

    /**
     * Get single location by ID
     *
     * @param int $id Location ID
     * @return array|null Location data or null if not found
     */
    public function getLocation(int $id): ?array
    {
        $this->db->where('id', $id);
        return $this->db->getOne('locations') ?: null;
    }

    /**
     * Create new location
     *
     * @param array $data Location data: name (required), description, color, icon, sort_order
     * @return int New location ID
     */
    public function createLocation(array $data): int
    {
        if (empty($data['name'])) {
            throw new Exception('Location name is required');
        }

        $insertData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#808080',
            'icon' => $data['icon'] ?? 'map-pin',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('locations', $insertData);
    }

    /**
     * Update location fields
     *
     * @param int $id Location ID
     * @param array $data Fields to update: name, description, color, icon, sort_order, is_active
     * @return bool Success status
     */
    public function updateLocation(int $id, array $data): bool
    {
        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['color'])) $updateData['color'] = $data['color'];
        if (isset($data['icon'])) $updateData['icon'] = $data['icon'];
        if (isset($data['sort_order'])) $updateData['sort_order'] = $data['sort_order'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        if (empty($updateData)) {
            return false;
        }

        $this->db->where('id', $id);
        return (bool) $this->db->update('locations', $updateData);
    }

    /**
     * Soft delete location (set is_active = 0)
     *
     * @param int $id Location ID
     * @return bool Success status
     */
    public function deleteLocation(int $id): bool
    {
        $this->db->where('id', $id);
        return (bool) $this->db->update('locations', [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Assign location to an asset or stock instance
     *
     * @param string $entityType 'asset' or 'stock_instance'
     * @param int $entityId Asset ID or stock instance ID
     * @param int|null $locationId ID from locations table (can be null if using custom text)
     * @param string|null $locationCustom Free text location (can be null if using predefined location)
     * @param int $userId User ID for audit trail
     * @param string|null $notes Optional notes
     * @return bool Success status
     */
    public function assignLocation(string $entityType, int $entityId, ?int $locationId, ?string $locationCustom, int $userId, ?string $notes = null): bool
    {
        // Validate input
        if (!in_array($entityType, ['asset', 'stock_instance'])) {
            return false;
        }

        if (is_null($locationId) && empty($locationCustom)) {
            return false;
        }

        // Get current location for logging
        $previousLocation = $this->getEntityLocation($entityType, $entityId);

        // Determine table and ID column based on entity type
        $tableName = $entityType === 'asset' ? 'assets' : 'stock_instances';
        $idColumn = $entityType === 'asset' ? 'assets_id' : 'id';

        // Update entity's location fields
        $this->db->where($idColumn, $entityId);
        $updated = $this->db->update($tableName, [
            'current_location_id' => $locationId,
            'current_location_custom' => $locationCustom,
            'location_updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            return false;
        }

        // Log the location change
        $this->db->insert('location_log', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'previous_location_id' => $previousLocation ? $previousLocation['location_id'] : null,
            'previous_location_custom' => $previousLocation ? $previousLocation['location_custom'] : null,
            'new_location_id' => $locationId,
            'new_location_custom' => $locationCustom,
            'changed_by' => $userId,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Get current location info for an entity
     *
     * @param string $entityType 'asset' or 'stock_instance'
     * @param int $entityId Asset ID or stock instance ID
     * @return array|null Location info with keys: location_id, location_name, location_custom, color, icon, updated_at
     */
    public function getEntityLocation(string $entityType, int $entityId): ?array
    {
        $tableName = $entityType === 'asset' ? 'assets' : 'stock_instances';
        $idColumn = $entityType === 'asset' ? 'assets_id' : 'id';

        // Join with locations table
        $this->db->join('locations l', 'l.id = ' . $tableName . '.current_location_id', 'LEFT');
        $this->db->where($tableName . '.' . $idColumn, $entityId);

        $result = $this->db->getOne($tableName . ' e', null, [
            'e.current_location_id',
            'e.current_location_custom',
            'e.location_updated_at',
            'l.name as location_name',
            'l.color',
            'l.icon',
        ]);

        if (!$result) {
            return null;
        }

        return [
            'location_id' => $result['current_location_id'],
            'location_name' => $result['location_name'] ?? ($result['current_location_custom'] ?? null),
            'location_custom' => $result['current_location_custom'],
            'color' => $result['color'] ?? '#808080',
            'icon' => $result['icon'] ?? 'map-pin',
            'updated_at' => $result['location_updated_at'],
        ];
    }

    /**
     * Get location change history for an entity
     *
     * @param string $entityType 'asset' or 'stock_instance'
     * @param int $entityId Asset ID or stock instance ID
     * @param int $limit Maximum number of entries to return
     * @return array Location history entries ordered by created_at DESC
     */
    public function getLocationHistory(string $entityType, int $entityId, int $limit = 20): array
    {
        $this->db->where('entity_type', $entityType);
        $this->db->where('entity_id', $entityId);
        $this->db->join('locations l', 'l.id = location_log.new_location_id', 'LEFT');
        $this->db->join('locations l_prev', 'l_prev.id = location_log.previous_location_id', 'LEFT');
        $this->db->orderBy('location_log.created_at', 'DESC');

        $results = $this->db->get('location_log', $limit, [
            'location_log.*',
            'l.name as new_location_name',
            'l.color as new_location_color',
            'l.icon as new_location_icon',
            'l_prev.name as previous_location_name',
        ]);

        return $results ?: [];
    }

    /**
     * Get all entities (assets and stock_instances) at a given location
     *
     * @param int $locationId Location ID
     * @return array With keys 'assets' and 'stock_instances' containing lists of entities
     */
    public function getEntitiesAtLocation(int $locationId): array
    {
        $assets = [];
        $stockInstances = [];

        // Get assets at this location
        $this->db->where('current_location_id', $locationId);
        $assets = $this->db->get('assets', null, [
            'assets_id',
            'assets_name',
            'assets_tag',
            'current_location_id',
            'current_location_custom',
            'location_updated_at',
        ]) ?: [];

        // Get stock instances at this location
        $this->db->where('current_location_id', $locationId);
        $stockInstances = $this->db->get('stock_instances', null, [
            'id',
            'item_name',
            'instance_number',
            'current_location_id',
            'current_location_custom',
            'location_updated_at',
        ]) ?: [];

        return [
            'assets' => $assets,
            'stock_instances' => $stockInstances,
        ];
    }

    /**
     * Process location scan: find entity by RFID tag and assign location
     *
     * @param string $rfidTag RFID tag to scan
     * @param int|null $locationId Predefined location ID
     * @param string|null $locationCustom Custom location text
     * @param int $userId User ID for audit trail
     * @param string|null $notes Optional notes
     * @return array Result with keys: success, entity_type, entity, location, message
     */
    public function processLocationScan(string $rfidTag, ?int $locationId, ?string $locationCustom, int $userId, ?string $notes = null): array
    {
        // Step 1: Find entity by RFID tag
        $entity = $this->findEntityByRfidTag($rfidTag);

        if (!$entity) {
            return [
                'success' => false,
                'entity_type' => null,
                'entity' => null,
                'location' => null,
                'message' => 'Tag nicht gefunden',
            ];
        }

        // Step 2: Assign location to the entity
        $entityType = $entity['entity_type'];
        $entityId = $entity['entity_id'];

        $assigned = $this->assignLocation(
            $entityType,
            $entityId,
            $locationId,
            $locationCustom,
            $userId,
            $notes
        );

        if (!$assigned) {
            return [
                'success' => false,
                'entity_type' => $entityType,
                'entity' => $entity,
                'location' => null,
                'message' => 'Fehler beim Zuweisen des Standorts',
            ];
        }

        // Step 3: Get updated location info
        $location = $this->getEntityLocation($entityType, $entityId);

        return [
            'success' => true,
            'entity_type' => $entityType,
            'entity' => $entity,
            'location' => $location,
            'message' => 'Standort zugewiesen',
        ];
    }

    /**
     * Process multiple RFID tags at once (bulk scan)
     *
     * @param array $rfidTags Array of RFID tags
     * @param int|null $locationId Predefined location ID
     * @param string|null $locationCustom Custom location text
     * @param int $userId User ID for audit trail
     * @param string|null $notes Optional notes
     * @return array Summary with keys: total, success, errors, details
     */
    public function bulkLocationScan(array $rfidTags, ?int $locationId, ?string $locationCustom, int $userId, ?string $notes = null): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($rfidTags as $tag) {
            $result = $this->processLocationScan($tag, $locationId, $locationCustom, $userId, $notes);

            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }

            $results[] = $result;
        }

        return [
            'total' => count($rfidTags),
            'success' => $successCount,
            'errors' => $errorCount,
            'details' => $results,
        ];
    }

    /**
     * Internal helper: Find entity by RFID tag
     *
     * @param string $rfidTag RFID tag
     * @return array|null Entity data with entity_type and entity_id, or null
     */
    private function findEntityByRfidTag(string $rfidTag): ?array
    {
        $rfidTag = trim($rfidTag);

        // Try to find in assets first (asset_definableFields_1 stores RFID)
        $this->db->where('asset_definableFields_1', $rfidTag);
        $asset = $this->db->getOne('assets', null, [
            'assets_id',
            'assets_name',
            'assets_tag',
        ]);

        if ($asset) {
            return [
                'entity_type' => 'asset',
                'entity_id' => $asset['assets_id'],
                'assets_id' => $asset['assets_id'],
                'display_name' => $asset['assets_name'] ?? '#' . $asset['assets_id'],
            ];
        }

        // Try stock instances
        $this->db->where('rfid_tag', $rfidTag);
        $stockInstance = $this->db->getOne('stock_instances', null, [
            'id',
            'item_name',
            'instance_number',
        ]);

        if ($stockInstance) {
            return [
                'entity_type' => 'stock_instance',
                'entity_id' => $stockInstance['id'],
                'id' => $stockInstance['id'],
                'display_name' => $stockInstance['item_name'] . ' #' . $stockInstance['instance_number'],
            ];
        }

        return null;
    }
}
