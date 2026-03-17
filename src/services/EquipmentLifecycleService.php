<?php
/**
 * EquipmentLifecycleService - Equipment-Lebenszyklus
 *
 * Verwaltet den vollstaendigen Lebenszyklus eines Assets:
 * Anschaffung → Inbetriebnahme → Betrieb → Wartung → Ausmusterung → Entsorgung/Verkauf
 */
class EquipmentLifecycleService
{
    private $db;

    const STATUS_ORDERED = 'ordered';
    const STATUS_RECEIVED = 'received';
    const STATUS_COMMISSIONED = 'commissioned';
    const STATUS_ACTIVE = 'active';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_REPAIR = 'repair';
    const STATUS_DECOMMISSIONED = 'decommissioned';
    const STATUS_DISPOSED = 'disposed';
    const STATUS_SOLD = 'sold';

    const VALID_STATUSES = [
        self::STATUS_ORDERED,
        self::STATUS_RECEIVED,
        self::STATUS_COMMISSIONED,
        self::STATUS_ACTIVE,
        self::STATUS_MAINTENANCE,
        self::STATUS_REPAIR,
        self::STATUS_DECOMMISSIONED,
        self::STATUS_DISPOSED,
        self::STATUS_SOLD,
    ];

    /** Erlaubte Statusuebergaenge */
    const TRANSITIONS = [
        self::STATUS_ORDERED => [self::STATUS_RECEIVED],
        self::STATUS_RECEIVED => [self::STATUS_COMMISSIONED, self::STATUS_DISPOSED],
        self::STATUS_COMMISSIONED => [self::STATUS_ACTIVE],
        self::STATUS_ACTIVE => [self::STATUS_MAINTENANCE, self::STATUS_REPAIR, self::STATUS_DECOMMISSIONED],
        self::STATUS_MAINTENANCE => [self::STATUS_ACTIVE, self::STATUS_DECOMMISSIONED],
        self::STATUS_REPAIR => [self::STATUS_ACTIVE, self::STATUS_DECOMMISSIONED],
        self::STATUS_DECOMMISSIONED => [self::STATUS_DISPOSED, self::STATUS_SOLD, self::STATUS_ACTIVE],
        self::STATUS_DISPOSED => [],
        self::STATUS_SOLD => [],
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Aktuellen Lifecycle-Status eines Assets abrufen
     */
    public function getStatus(int $assetId): ?array
    {
        $this->db->where('assets_id', $assetId);
        $this->db->where('assets_deleted', 0);
        $asset = $this->db->getOne('assets', null, [
            'assets_id',
            'assets_lifecycle_status',
            'assets_lifecycle_ordered_date',
            'assets_lifecycle_received_date',
            'assets_lifecycle_commissioned_date',
            'assets_lifecycle_decommissioned_date',
            'assets_lifecycle_disposed_date',
            'assets_lifecycle_notes',
            'assets_lifecycle_purchase_price',
            'assets_lifecycle_sale_price',
        ]);

        if (!$asset) return null;

        return [
            'asset_id' => $asset['assets_id'],
            'status' => $asset['assets_lifecycle_status'] ?? self::STATUS_ACTIVE,
            'ordered_date' => $asset['assets_lifecycle_ordered_date'] ?? null,
            'received_date' => $asset['assets_lifecycle_received_date'] ?? null,
            'commissioned_date' => $asset['assets_lifecycle_commissioned_date'] ?? null,
            'decommissioned_date' => $asset['assets_lifecycle_decommissioned_date'] ?? null,
            'disposed_date' => $asset['assets_lifecycle_disposed_date'] ?? null,
            'notes' => $asset['assets_lifecycle_notes'] ?? '',
            'purchase_price' => $asset['assets_lifecycle_purchase_price'] ?? null,
            'sale_price' => $asset['assets_lifecycle_sale_price'] ?? null,
            'allowed_transitions' => self::TRANSITIONS[$asset['assets_lifecycle_status'] ?? self::STATUS_ACTIVE] ?? [],
        ];
    }

    /**
     * Lifecycle-Status aendern
     */
    public function transition(int $assetId, string $newStatus, int $userId, array $data = []): array
    {
        if (!in_array($newStatus, self::VALID_STATUSES)) {
            return ['success' => false, 'error' => 'Ungueltiger Status: ' . $newStatus];
        }

        $current = $this->getStatus($assetId);
        if (!$current) {
            return ['success' => false, 'error' => 'Asset nicht gefunden'];
        }

        $currentStatus = $current['status'];
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return [
                'success' => false,
                'error' => "Uebergang von '{$currentStatus}' zu '{$newStatus}' nicht erlaubt",
                'allowed' => $allowed,
            ];
        }

        $updateData = ['assets_lifecycle_status' => $newStatus];

        // Datumsfelder automatisch setzen
        $dateField = $this->getDateFieldForStatus($newStatus);
        if ($dateField) {
            $updateData[$dateField] = date('Y-m-d H:i:s');
        }

        if (isset($data['notes'])) {
            $updateData['assets_lifecycle_notes'] = $data['notes'];
        }
        if (isset($data['purchase_price']) && $newStatus === self::STATUS_RECEIVED) {
            $updateData['assets_lifecycle_purchase_price'] = floatval($data['purchase_price']);
        }
        if (isset($data['sale_price']) && $newStatus === self::STATUS_SOLD) {
            $updateData['assets_lifecycle_sale_price'] = floatval($data['sale_price']);
        }

        $this->db->where('assets_id', $assetId);
        $this->db->update('assets', $updateData);

        // Log eintragen
        $this->logTransition($assetId, $currentStatus, $newStatus, $userId, $data['notes'] ?? '');

        return ['success' => true, 'new_status' => $newStatus];
    }

    /**
     * Lifecycle-Historie eines Assets
     */
    public function getHistory(int $assetId): array
    {
        $this->db->where('asset_id', $assetId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('asset_lifecycle_log', null, [
            'id', 'from_status', 'to_status', 'user_id', 'notes', 'created_at'
        ]) ?: [];
    }

    /**
     * Assets nach Lifecycle-Status filtern
     */
    public function getByStatus(string $status, int $instanceId, int $limit = 50): array
    {
        $this->db->join('assetTypes at', 'at.assetTypes_id = a.assetTypes_id', 'LEFT');
        $this->db->where('a.assets_lifecycle_status', $status);
        $this->db->where('a.assets_deleted', 0);
        $this->db->where('at.instances_id', $instanceId);
        $this->db->orderBy('a.assets_id', 'DESC');
        return $this->db->get('assets a', $limit, [
            'a.assets_id',
            'a.assets_tag',
            'at.assetTypes_name',
            'a.assets_lifecycle_status',
            'a.assets_lifecycle_purchase_price',
            'a.assets_lifecycle_decommissioned_date',
        ]) ?: [];
    }

    /**
     * Zusammenfassung: Wie viele Assets in welchem Status
     */
    public function getSummary(int $instanceId): array
    {
        $sql = "SELECT COALESCE(a.assets_lifecycle_status, 'active') as status, COUNT(*) as count
                FROM assets a
                JOIN assetTypes at ON at.assetTypes_id = a.assetTypes_id
                WHERE a.assets_deleted = 0 AND at.instances_id = ?
                GROUP BY COALESCE(a.assets_lifecycle_status, 'active')";
        $results = $this->db->rawQuery($sql, [$instanceId]);

        $summary = array_fill_keys(self::VALID_STATUSES, 0);
        foreach ($results ?: [] as $row) {
            $summary[$row['status']] = (int) $row['count'];
        }
        return $summary;
    }

    private function getDateFieldForStatus(string $status): ?string
    {
        $map = [
            self::STATUS_ORDERED => 'assets_lifecycle_ordered_date',
            self::STATUS_RECEIVED => 'assets_lifecycle_received_date',
            self::STATUS_COMMISSIONED => 'assets_lifecycle_commissioned_date',
            self::STATUS_DECOMMISSIONED => 'assets_lifecycle_decommissioned_date',
            self::STATUS_DISPOSED => 'assets_lifecycle_disposed_date',
            self::STATUS_SOLD => 'assets_lifecycle_disposed_date',
        ];
        return $map[$status] ?? null;
    }

    private function logTransition(int $assetId, string $from, string $to, int $userId, string $notes = ''): void
    {
        $this->db->insert('asset_lifecycle_log', [
            'asset_id' => $assetId,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
