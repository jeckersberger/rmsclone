<?php
/**
 * ExternalItemService — Verwaltung von Fremdmaterial
 *
 * Handles CRUD for external/borrowed equipment that doesn't belong to the organization.
 * Supports barcode/RFID assignment, project association, and return tracking.
 */
class ExternalItemService
{
    private $db;
    private ?TagFormatService $tagFormatService = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function setTagFormatService(TagFormatService $svc): void
    {
        $this->tagFormatService = $svc;
    }

    // ══════════════════════════════════════
    // CRUD Operations
    // ══════════════════════════════════════

    /**
     * Create a new external item
     */
    public function create(int $instanceId, array $data): ?int
    {
        $insertData = [
            'instances_id'    => $instanceId,
            'description'     => trim($data['description'] ?? ''),
            'owner_name'      => trim($data['owner_name'] ?? ''),
            'owner_contact'   => !empty($data['owner_contact']) ? trim($data['owner_contact']) : null,
            'quantity'        => max(1, (int)($data['quantity'] ?? 1)),
            'barcode'         => !empty($data['barcode']) ? trim($data['barcode']) : null,
            'rfid_tag'        => !empty($data['rfid_tag']) ? trim($data['rfid_tag']) : null,
            'project_id'      => !empty($data['project_id']) ? (int)$data['project_id'] : null,
            'location_id'     => !empty($data['location_id']) ? (int)$data['location_id'] : null,
            'location_custom' => !empty($data['location_custom']) ? trim($data['location_custom']) : null,
            'status'          => 'bei_uns',
            'return_date'     => !empty($data['return_date']) ? $data['return_date'] : null,
            'notes'           => !empty($data['notes']) ? trim($data['notes']) : null,
            'received_by'     => !empty($data['received_by']) ? (int)$data['received_by'] : null,
        ];

        if (empty($insertData['description'])) return null;
        if (empty($insertData['owner_name'])) return null;

        // Auto-generate barcode if not provided
        if (empty($insertData['barcode'])) {
            $insertData['barcode'] = $this->generateBarcode();
        }

        // Check barcode uniqueness
        if (!empty($insertData['barcode'])) {
            $this->db->where('barcode', $insertData['barcode']);
            $existing = $this->db->getOne('external_items', ['id']);
            if ($existing) return null;
        }

        // Check RFID tag uniqueness across all entity types
        if (!empty($insertData['rfid_tag'])) {
            if (!$this->isRfidTagAvailable($insertData['rfid_tag'])) return null;
        }

        $id = $this->db->insert('external_items', $insertData);
        return $id ?: null;
    }

    /**
     * Update an external item
     */
    public function update(int $id, array $data): bool
    {
        $updateData = [];

        $allowedFields = [
            'description', 'owner_name', 'owner_contact', 'quantity',
            'barcode', 'rfid_tag', 'project_id', 'location_id',
            'location_custom', 'status', 'return_date', 'notes'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                if (is_string($val)) $val = trim($val);
                $updateData[$field] = ($val === '' || $val === null) ? null : $val;
            }
        }

        if (empty($updateData)) return false;

        // If marking as returned
        if (isset($updateData['status']) && $updateData['status'] === 'zurueckgegeben') {
            $updateData['returned_at'] = date('Y-m-d H:i:s');
            if (!empty($data['returned_by'])) {
                $updateData['returned_by'] = (int)$data['returned_by'];
            }
        }

        $this->db->where('id', $id);
        return $this->db->update('external_items', $updateData);
    }

    /**
     * Delete an external item
     */
    public function delete(int $id): bool
    {
        $this->db->where('id', $id);
        return $this->db->delete('external_items');
    }

    /**
     * Get a single external item by ID
     */
    public function get(int $id): ?array
    {
        $this->db->where('e.id', $id);
        $this->db->join('locations l', 'e.location_id=l.id', 'LEFT');
        $this->db->join('projects p', 'e.project_id=p.projects_id', 'LEFT');
        $item = $this->db->getOne('external_items e', [
            'e.*',
            'l.name AS location_name',
            'l.color AS location_color',
            'p.projects_name AS project_name'
        ]);
        return $item ?: null;
    }

    /**
     * List all external items with optional filters
     */
    public function listItems(int $instanceId, array $filters = []): array
    {
        $this->db->where('e.instances_id', $instanceId);

        if (!empty($filters['status'])) {
            $this->db->where('e.status', $filters['status']);
        }
        if (!empty($filters['owner_name'])) {
            $this->db->where('e.owner_name', '%' . $filters['owner_name'] . '%', 'LIKE');
        }
        if (!empty($filters['project_id'])) {
            $this->db->where('e.project_id', (int)$filters['project_id']);
        }
        if (!empty($filters['search'])) {
            $this->db->where('(e.description LIKE ? OR e.owner_name LIKE ? OR e.barcode LIKE ?)',
                ['%' . $filters['search'] . '%', '%' . $filters['search'] . '%', '%' . $filters['search'] . '%']);
        }

        $this->db->join('locations l', 'e.location_id=l.id', 'LEFT');
        $this->db->join('projects p', 'e.project_id=p.projects_id', 'LEFT');
        $this->db->orderBy('e.created_at', 'DESC');

        $items = $this->db->get('external_items e', null, [
            'e.*',
            'l.name AS location_name',
            'l.color AS location_color',
            'p.projects_name AS project_name'
        ]);

        return $items ?: [];
    }

    /**
     * Find external item by barcode
     */
    public function findByBarcode(string $barcode): ?array
    {
        $this->db->where('barcode', $barcode);
        $this->db->join('locations l', 'e.location_id=l.id', 'LEFT');
        $this->db->join('projects p', 'e.project_id=p.projects_id', 'LEFT');
        $item = $this->db->getOne('external_items e', [
            'e.*',
            'l.name AS location_name',
            'p.projects_name AS project_name'
        ]);
        return $item ?: null;
    }

    /**
     * Find external item by RFID tag
     */
    public function findByRfidTag(string $rfidTag): ?array
    {
        $this->db->where('rfid_tag', $rfidTag);
        $this->db->join('locations l', 'e.location_id=l.id', 'LEFT');
        $this->db->join('projects p', 'e.project_id=p.projects_id', 'LEFT');
        $item = $this->db->getOne('external_items e', [
            'e.*',
            'l.name AS location_name',
            'p.projects_name AS project_name'
        ]);
        return $item ?: null;
    }

    /**
     * Find by barcode OR rfid_tag (universal scan)
     */
    public function findByScan(string $scanValue): ?array
    {
        // Try barcode first
        $item = $this->findByBarcode($scanValue);
        if ($item) return $item;

        // Then RFID
        return $this->findByRfidTag($scanValue);
    }

    // ══════════════════════════════════════
    // Return Management
    // ══════════════════════════════════════

    /**
     * Mark an external item as returned
     */
    public function markReturned(int $id, int $userId): bool
    {
        return $this->update($id, [
            'status' => 'zurueckgegeben',
            'returned_by' => $userId,
        ]);
    }

    /**
     * Mark an external item as lost
     */
    public function markLost(int $id): bool
    {
        return $this->update($id, ['status' => 'verloren']);
    }

    // ══════════════════════════════════════
    // Barcode Generation
    // ══════════════════════════════════════

    /**
     * Generate a unique barcode for external items: RMS-ABCDE1-E-xxxxxx or fallback to EXT-xxxxxx
     */
    public function generateBarcode(): string
    {
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $number = $this->getNextBarcodeNumber();

            // Use TagFormatService if available
            if ($this->tagFormatService) {
                $barcode = $this->tagFormatService->generateExternalBarcode($number);
            } else {
                $barcode = 'EXT-' . str_pad($number, 6, '0', STR_PAD_LEFT); // fallback
            }

            $this->db->where('barcode', $barcode);
            $exists = $this->db->getOne('external_items', ['id']);
            if (!$exists) return $barcode;
        }
        // Fallback with timestamp
        return 'EXT-' . time();
    }

    private function getNextBarcodeNumber(): int
    {
        $this->db->orderBy('id', 'DESC');
        $last = $this->db->getOne('external_items', ['id', 'barcode']);
        if (!$last || empty($last['barcode'])) return 1;

        // Extract number from EXT-xxxxxx
        if (preg_match('/^EXT-(\d+)$/', $last['barcode'], $m)) {
            return (int)$m[1] + 1;
        }
        return $last['id'] + 1;
    }

    // ══════════════════════════════════════
    // RFID Tag Uniqueness Check
    // ══════════════════════════════════════

    /**
     * Check if RFID tag is available (not used by any entity type)
     */
    private function isRfidTagAvailable(string $rfidTag): bool
    {
        // Check external_items
        $this->db->where('rfid_tag', $rfidTag);
        if ($this->db->getOne('external_items', ['id'])) return false;

        // Check assets
        $this->db->where('asset_definableFields_1', $rfidTag);
        if ($this->db->getOne('assets', ['assets_id'])) return false;

        // Check stock_instances
        $this->db->where('rfid_tag', $rfidTag);
        if ($this->db->getOne('stock_instances', ['id'])) return false;

        return true;
    }

    // ══════════════════════════════════════
    // Statistics
    // ══════════════════════════════════════

    /**
     * Get overview statistics
     */
    public function getStats(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $total = $this->db->getValue('external_items', 'COUNT(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'bei_uns');
        $beiUns = $this->db->getValue('external_items', 'COUNT(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'zurueckgegeben');
        $returned = $this->db->getValue('external_items', 'COUNT(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'verloren');
        $lost = $this->db->getValue('external_items', 'COUNT(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'bei_uns');
        $this->db->where('return_date', date('Y-m-d'), '<=');
        $this->db->where('return_date IS NOT NULL');
        $overdue = $this->db->getValue('external_items', 'COUNT(*)');

        return [
            'total' => (int)$total,
            'bei_uns' => (int)$beiUns,
            'zurueckgegeben' => (int)$returned,
            'verloren' => (int)$lost,
            'ueberfaellig' => (int)$overdue,
        ];
    }
}
