<?php
/**
 * Partner-Kooperation Service
 *
 * Ermoeglicht die Zusammenarbeit zwischen zwei Kleingewerben:
 * - Gegenseitige Equipment-Sichtbarkeit
 * - Partner-Vermietpreise
 * - Cross-Business Equipment-Anfragen
 * - Gemeinsamer Verfuegbarkeitskalender
 *
 * Tabellen:
 *   partner_links        - Partnerschaft zwischen zwei instances
 *   partner_price_rules  - Sonderpreise fuer Partnerverleih
 *   partner_requests     - Equipment-Anfragen zwischen Partnern
 */
class PartnerService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ── Partnership Management ──

    /**
     * Send a partnership invitation
     */
    public function sendInvitation(int $fromInstanceId, string $partnerCode, int $userId): array
    {
        // Find the partner by their unique code
        $this->db->where('instances_partnerCode', $partnerCode);
        $this->db->where('instances_deleted', 0);
        $partner = $this->db->getOne('instances', ['instances_id', 'instances_name']);
        if (!$partner) return ['success' => false, 'error' => 'partner_not_found'];
        if ($partner['instances_id'] == $fromInstanceId) return ['success' => false, 'error' => 'self_link'];

        // Check for existing link
        $this->db->where('(instance_a_id = ? AND instance_b_id = ?) OR (instance_a_id = ? AND instance_b_id = ?)',
            [$fromInstanceId, $partner['instances_id'], $partner['instances_id'], $fromInstanceId]);
        $this->db->where('deleted', 0);
        $existing = $this->db->getOne('partner_links');
        if ($existing) return ['success' => false, 'error' => 'already_linked'];

        $this->db->insert('partner_links', [
            'instance_a_id' => $fromInstanceId,
            'instance_b_id' => $partner['instances_id'],
            'status' => 'pending',
            'invited_by' => $userId,
        ]);

        return ['success' => true, 'partner_name' => $partner['instances_name']];
    }

    /**
     * Accept a partnership invitation
     */
    public function acceptInvitation(int $linkId, int $instanceId): bool
    {
        $this->db->where('id', $linkId);
        $this->db->where('instance_b_id', $instanceId);
        $this->db->where('status', 'pending');
        return $this->db->update('partner_links', ['status' => 'active', 'accepted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Reject or remove a partnership
     */
    public function removePartnership(int $linkId, int $instanceId): bool
    {
        $this->db->where('id', $linkId);
        $this->db->where('(instance_a_id = ? OR instance_b_id = ?)', [$instanceId, $instanceId]);
        return $this->db->update('partner_links', ['deleted' => 1]);
    }

    /**
     * Get all active partnerships for an instance
     */
    public function getPartnerships(int $instanceId): array
    {
        $sql = "SELECT pl.*,
                CASE WHEN pl.instance_a_id = ? THEN ib.instances_name ELSE ia.instances_name END as partner_name,
                CASE WHEN pl.instance_a_id = ? THEN pl.instance_b_id ELSE pl.instance_a_id END as partner_id
                FROM partner_links pl
                LEFT JOIN instances ia ON pl.instance_a_id = ia.instances_id
                LEFT JOIN instances ib ON pl.instance_b_id = ib.instances_id
                WHERE (pl.instance_a_id = ? OR pl.instance_b_id = ?)
                AND pl.deleted = 0
                ORDER BY pl.created_at DESC";
        return $this->db->rawQuery($sql, [$instanceId, $instanceId, $instanceId, $instanceId]) ?: [];
    }

    /**
     * Get pending invitations for an instance
     */
    public function getPendingInvitations(int $instanceId): array
    {
        $this->db->where('instance_b_id', $instanceId);
        $this->db->where('status', 'pending');
        $this->db->where('deleted', 0);
        $this->db->join('instances', 'partner_links.instance_a_id = instances.instances_id', 'LEFT');
        return $this->db->get('partner_links', null, ['partner_links.*', 'instances.instances_name as from_name']) ?: [];
    }

    // ── Partner Equipment Visibility ──

    /**
     * Get available equipment from all active partners
     */
    public function getPartnerEquipment(int $instanceId, ?string $startDate = null, ?string $endDate = null, ?string $searchTerm = null): array
    {
        $partners = $this->getPartnerships($instanceId);
        $activePartnerIds = [];
        foreach ($partners as $p) {
            if ($p['status'] === 'active') $activePartnerIds[] = $p['partner_id'];
        }
        if (empty($activePartnerIds)) return [];

        $placeholders = implode(',', array_fill(0, count($activePartnerIds), '?'));

        $sql = "SELECT at.assetTypes_id, at.assetTypes_name,
                       ac.assetCategories_name,
                       at.assetTypes_dayRate, at.assetTypes_weekRate,
                       i.instances_id, i.instances_name,
                       COUNT(a.assets_id) as total_count
                FROM assetTypes at
                JOIN assets a ON at.assetTypes_id = a.assetTypes_id AND a.assets_deleted = 0
                LEFT JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                JOIN instances i ON at.instances_id = i.instances_id
                WHERE at.instances_id IN ($placeholders)
                AND at.assetTypes_deleted = 0";
        $params = $activePartnerIds;

        if ($searchTerm) {
            $sql .= " AND (at.assetTypes_name LIKE ? OR ac.assetCategories_name LIKE ?)";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
        }

        $sql .= " GROUP BY at.assetTypes_id ORDER BY ac.assetCategories_rank ASC, at.assetTypes_name ASC";

        $equipment = $this->db->rawQuery($sql, $params) ?: [];

        // Check availability if dates provided
        if ($startDate && $endDate) {
            $availSvc = new AvailabilityService($this->db);
            foreach ($equipment as &$eq) {
                $avail = $availSvc->getAssetTypeAvailability($eq['assetTypes_id'], $eq['instances_id'], $startDate, $endDate);
                $eq['available_count'] = $avail['available'];
                $eq['unavailable_count'] = $avail['unavailable'];
            }
        }

        // Apply partner pricing
        foreach ($equipment as &$eq) {
            $priceRule = $this->getPartnerPrice($instanceId, $eq['instances_id'], $eq['assetTypes_id']);
            if ($priceRule) {
                $eq['partner_day_rate'] = $priceRule['day_rate'];
                $eq['partner_week_rate'] = $priceRule['week_rate'];
                $eq['partner_discount_pct'] = $priceRule['discount_pct'];
            } else {
                $eq['partner_day_rate'] = $eq['assetTypes_dayRate'];
                $eq['partner_week_rate'] = $eq['assetTypes_weekRate'];
                $eq['partner_discount_pct'] = 0;
            }
        }

        return $equipment;
    }

    // ── Partner Pricing ──

    /**
     * Get partner price for a specific asset type
     */
    public function getPartnerPrice(int $myInstanceId, int $partnerInstanceId, int $assetTypeId): ?array
    {
        // Check specific asset type price first
        $this->db->where('owner_instance_id', $partnerInstanceId);
        $this->db->where('partner_instance_id', $myInstanceId);
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('deleted', 0);
        $specific = $this->db->getOne('partner_price_rules');
        if ($specific) return $specific;

        // Fallback: global partner discount
        $this->db->where('owner_instance_id', $partnerInstanceId);
        $this->db->where('partner_instance_id', $myInstanceId);
        $this->db->where('assetTypes_id IS NULL');
        $this->db->where('deleted', 0);
        return $this->db->getOne('partner_price_rules');
    }

    /**
     * Set partner pricing
     */
    public function setPartnerPrice(int $ownerInstanceId, int $partnerInstanceId, ?int $assetTypeId, array $pricing): bool
    {
        // Check/update existing
        $this->db->where('owner_instance_id', $ownerInstanceId);
        $this->db->where('partner_instance_id', $partnerInstanceId);
        if ($assetTypeId) {
            $this->db->where('assetTypes_id', $assetTypeId);
        } else {
            $this->db->where('assetTypes_id IS NULL');
        }
        $this->db->where('deleted', 0);
        $existing = $this->db->getOne('partner_price_rules');

        $data = [
            'owner_instance_id' => $ownerInstanceId,
            'partner_instance_id' => $partnerInstanceId,
            'assetTypes_id' => $assetTypeId,
            'day_rate' => $pricing['day_rate'] ?? null,
            'week_rate' => $pricing['week_rate'] ?? null,
            'discount_pct' => $pricing['discount_pct'] ?? 0,
        ];

        if ($existing) {
            $this->db->where('id', $existing['id']);
            return $this->db->update('partner_price_rules', $data);
        }
        return (bool)$this->db->insert('partner_price_rules', $data);
    }

    // ── Equipment Requests ──

    /**
     * Create an equipment request to a partner
     */
    public function createRequest(int $fromInstanceId, int $toInstanceId, int $projectId, array $items, string $startDate, string $endDate, int $userId): int
    {
        $this->db->insert('partner_requests', [
            'from_instance_id' => $fromInstanceId,
            'to_instance_id' => $toInstanceId,
            'projects_id' => $projectId,
            'status' => 'pending',
            'date_start' => $startDate,
            'date_end' => $endDate,
            'notes' => $items['notes'] ?? null,
            'created_by' => $userId,
        ]);
        $requestId = $this->db->getInsertId();

        foreach ($items['equipment'] as $item) {
            $this->db->insert('partner_request_items', [
                'partner_requests_id' => $requestId,
                'assetTypes_id' => $item['assetTypes_id'],
                'quantity' => $item['quantity'],
                'day_rate' => $item['day_rate'] ?? null,
            ]);
        }

        return $requestId;
    }

    /**
     * Get requests for an instance (incoming and outgoing)
     */
    public function getRequests(int $instanceId, string $direction = 'both'): array
    {
        $sql = "SELECT pr.*,
                i_from.instances_name as from_name,
                i_to.instances_name as to_name,
                p.projects_name
                FROM partner_requests pr
                LEFT JOIN instances i_from ON pr.from_instance_id = i_from.instances_id
                LEFT JOIN instances i_to ON pr.to_instance_id = i_to.instances_id
                LEFT JOIN projects p ON pr.projects_id = p.projects_id
                WHERE pr.deleted = 0";
        $params = [];

        if ($direction === 'incoming') {
            $sql .= " AND pr.to_instance_id = ?";
            $params[] = $instanceId;
        } elseif ($direction === 'outgoing') {
            $sql .= " AND pr.from_instance_id = ?";
            $params[] = $instanceId;
        } else {
            $sql .= " AND (pr.from_instance_id = ? OR pr.to_instance_id = ?)";
            $params[] = $instanceId;
            $params[] = $instanceId;
        }

        $sql .= " ORDER BY pr.created_at DESC";
        $requests = $this->db->rawQuery($sql, $params) ?: [];

        // Load items for each request
        foreach ($requests as &$r) {
            $this->db->where('partner_requests_id', $r['id']);
            $this->db->join('assetTypes', 'partner_request_items.assetTypes_id = assetTypes.assetTypes_id', 'LEFT');
            $r['items'] = $this->db->get('partner_request_items', null, ['partner_request_items.*', 'assetTypes.assetTypes_name']) ?: [];
        }

        return $requests;
    }

    /**
     * Accept/reject a partner request
     */
    public function updateRequestStatus(int $requestId, int $instanceId, string $status, ?string $response = null): bool
    {
        $this->db->where('id', $requestId);
        $this->db->where('to_instance_id', $instanceId);
        return $this->db->update('partner_requests', [
            'status' => $status,
            'response_notes' => $response,
            'responded_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Generate a unique partner code for an instance
     */
    public function generatePartnerCode(int $instanceId): string
    {
        $code = strtoupper(substr(md5($instanceId . time()), 0, 8));
        $this->db->where('instances_id', $instanceId);
        $this->db->update('instances', ['instances_partnerCode' => $code]);
        return $code;
    }
}
