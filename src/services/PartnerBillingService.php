<?php
/**
 * PartnerBillingService - Partner-Kooperations-Abrechnung
 *
 * Verwaltet:
 * - Partner-Preisabstimmung (Synchronisation der Preise)
 * - Partner-Auftraege (Cross-Business Projekte)
 * - Mieteinnahmen-Aufteilung
 * - Partner-Verfuegbarkeits-Synchronisation
 */
class PartnerBillingService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ── Partner-Preisabstimmung ──

    /**
     * Synchronisiert Preise eines Partners mit den aktuellen Katalogpreisen.
     * Aktualisiert alle partner_price_rules ohne expliziten Preis (discount_pct basiert).
     */
    public function syncPartnerPrices(int $ownerInstanceId, int $partnerInstanceId): array
    {
        $this->db->where('owner_instance_id', $ownerInstanceId);
        $this->db->where('partner_instance_id', $partnerInstanceId);
        $this->db->where('deleted', 0);
        $this->db->where('assetTypes_id IS NOT NULL');
        $rules = $this->db->get('partner_price_rules') ?: [];

        $updated = 0;
        foreach ($rules as $rule) {
            if ($rule['discount_pct'] > 0) {
                // Hole aktuellen Katalogpreis
                $this->db->where('assetTypes_id', $rule['assetTypes_id']);
                $assetType = $this->db->getOne('assetTypes', null, ['assetTypes_dayRate', 'assetTypes_weekRate']);
                if (!$assetType) continue;

                $discountFactor = 1 - ($rule['discount_pct'] / 100);
                $newDayRate = round($assetType['assetTypes_dayRate'] * $discountFactor, 2);
                $newWeekRate = $assetType['assetTypes_weekRate']
                    ? round($assetType['assetTypes_weekRate'] * $discountFactor, 2)
                    : null;

                $this->db->where('id', $rule['id']);
                $this->db->update('partner_price_rules', [
                    'day_rate' => $newDayRate,
                    'week_rate' => $newWeekRate,
                ]);
                $updated++;
            }
        }

        return ['synced' => $updated, 'total_rules' => count($rules)];
    }

    // ── Partner-Auftraege ──

    /**
     * Erstellt einen Partner-Auftrag (Cross-Business Projekt)
     */
    public function createPartnerOrder(int $fromInstanceId, int $toInstanceId, int $requestId, array $data): int
    {
        $this->db->insert('partner_orders', [
            'from_instance_id' => $fromInstanceId,
            'to_instance_id' => $toInstanceId,
            'partner_requests_id' => $requestId,
            'order_number' => $this->generateOrderNumber($fromInstanceId),
            'status' => 'confirmed',
            'total_amount' => $data['total_amount'] ?? 0,
            'commission_pct' => $data['commission_pct'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->getInsertId();
    }

    /**
     * Berechnet die Mieteinnahmen-Aufteilung fuer einen Partner-Auftrag
     */
    public function calculateRevenueSplit(int $orderId): array
    {
        $this->db->where('id', $orderId);
        $order = $this->db->getOne('partner_orders', null);
        if (!$order) return ['error' => 'Order not found'];

        $totalAmount = (float) $order['total_amount'];
        $commissionPct = (float) $order['commission_pct'];

        $ownerShare = round($totalAmount * (1 - $commissionPct / 100), 2);
        $partnerShare = round($totalAmount * ($commissionPct / 100), 2);

        return [
            'total_amount' => $totalAmount,
            'owner_share' => $ownerShare,
            'partner_share' => $partnerShare,
            'commission_pct' => $commissionPct,
        ];
    }

    /**
     * Partner-Auftraege auflisten
     */
    public function getOrders(int $instanceId, string $direction = 'both'): array
    {
        $sql = "SELECT po.*, pr.date_start, pr.date_end,
                i_from.instances_name as from_name,
                i_to.instances_name as to_name
                FROM partner_orders po
                JOIN partner_requests pr ON po.partner_requests_id = pr.id
                LEFT JOIN instances i_from ON po.from_instance_id = i_from.instances_id
                LEFT JOIN instances i_to ON po.to_instance_id = i_to.instances_id
                WHERE po.deleted = 0";
        $params = [];

        if ($direction === 'incoming') {
            $sql .= " AND po.to_instance_id = ?";
            $params[] = $instanceId;
        } elseif ($direction === 'outgoing') {
            $sql .= " AND po.from_instance_id = ?";
            $params[] = $instanceId;
        } else {
            $sql .= " AND (po.from_instance_id = ? OR po.to_instance_id = ?)";
            $params[] = $instanceId;
            $params[] = $instanceId;
        }

        $sql .= " ORDER BY po.created_at DESC";
        return $this->db->rawQuery($sql, $params) ?: [];
    }

    // ── Partner-Verfuegbarkeit ──

    /**
     * Holt die Verfuegbarkeit aller Partner-Assets fuer einen Zeitraum
     */
    public function getPartnerAvailability(int $instanceId, string $startDate, string $endDate): array
    {
        $partnerService = new PartnerService($this->db);
        $equipment = $partnerService->getPartnerEquipment($instanceId, $startDate, $endDate);

        $calendar = [];
        foreach ($equipment as $eq) {
            $calendar[] = [
                'asset_type_id' => $eq['assetTypes_id'],
                'asset_type_name' => $eq['assetTypes_name'],
                'partner_name' => $eq['instances_name'],
                'total_count' => $eq['total_count'],
                'available_count' => $eq['available_count'] ?? $eq['total_count'],
                'day_rate' => $eq['partner_day_rate'],
            ];
        }
        return $calendar;
    }

    /**
     * Generiert eine Partner-Auftragsnummer
     */
    private function generateOrderNumber(int $instanceId): string
    {
        $year = date('Y');
        $this->db->where('from_instance_id', $instanceId);
        $this->db->where('order_number LIKE ?', ["PA-{$year}-%"]);
        $count = (int) $this->db->getValue('partner_orders', 'count(*)');
        return sprintf('PA-%s-%04d', $year, $count + 1);
    }
}
