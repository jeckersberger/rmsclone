<?php
/**
 * Kundenspezifische Preislisten
 *
 * Ermoeglicht individuelle Preise pro Kunde/Kundengruppe:
 * - Pauschalrabatte (z.B. 10% auf alles)
 * - Asset-spezifische Preise
 * - Staffelpreise ab X Tagen
 *
 * Tabellen:
 *   client_price_rules     - Preisregeln pro Kunde
 */
class CustomerPricingService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get effective price for a client + asset type combination
     */
    public function getEffectivePrice(int $clientId, int $assetTypeId, int $days = 1): array
    {
        // 1) Check specific asset type price for this client using rawQuery for complex OR
        $sql = "SELECT * FROM client_price_rules
                WHERE clients_id = ?
                AND assetTypes_id = ?
                AND deleted = 0
                AND (min_days IS NULL OR min_days <= ?)
                ORDER BY min_days DESC
                LIMIT 1";
        $result = $this->db->rawQuery($sql, [$clientId, $assetTypeId, $days]);
        $specific = $result ? $result[0] : null;

        if ($specific) {
            return [
                'type' => 'specific',
                'day_rate' => $specific['day_rate'],
                'week_rate' => $specific['week_rate'],
                'discount_pct' => $specific['discount_pct'],
                'rule_name' => $specific['rule_name'],
            ];
        }

        // 2) Check global discount for this client
        $this->db->where('clients_id', $clientId);
        $this->db->where('assetTypes_id IS NULL');
        $this->db->where('deleted', 0);
        $global = $this->db->getOne('client_price_rules');

        if ($global) {
            return [
                'type' => 'global_discount',
                'day_rate' => null,
                'week_rate' => null,
                'discount_pct' => $global['discount_pct'],
                'rule_name' => $global['rule_name'],
            ];
        }

        return ['type' => 'standard', 'discount_pct' => 0];
    }

    /**
     * Get all price rules for a client
     */
    public function getClientRules(int $clientId): array
    {
        $this->db->where('clients_id', $clientId);
        $this->db->where('deleted', 0);
        $this->db->join('assetTypes', 'client_price_rules.assetTypes_id = assetTypes.assetTypes_id', 'LEFT');
        $this->db->orderBy('assetTypes.assetTypes_name', 'ASC');
        return $this->db->get('client_price_rules', null, ['client_price_rules.*', 'assetTypes.assetTypes_name']) ?: [];
    }

    /**
     * Add/update a price rule
     */
    public function setRule(int $clientId, ?int $assetTypeId, array $data): int
    {
        // Check for existing rule
        $this->db->where('clients_id', $clientId);
        if ($assetTypeId) {
            $this->db->where('assetTypes_id', $assetTypeId);
        } else {
            $this->db->where('assetTypes_id IS NULL');
        }
        $this->db->where('deleted', 0);
        $existing = $this->db->getOne('client_price_rules');

        $row = [
            'clients_id' => $clientId,
            'assetTypes_id' => $assetTypeId,
            'rule_name' => $data['rule_name'] ?? null,
            'day_rate' => $data['day_rate'] ?? null,
            'week_rate' => $data['week_rate'] ?? null,
            'discount_pct' => $data['discount_pct'] ?? 0,
            'min_days' => $data['min_days'] ?? null,
        ];

        if ($existing) {
            $this->db->where('id', $existing['id']);
            $this->db->update('client_price_rules', $row);
            return $existing['id'];
        }
        return $this->db->insert('client_price_rules', $row);
    }

    /**
     * Delete a price rule
     */
    public function deleteRule(int $ruleId): bool
    {
        $this->db->where('id', $ruleId);
        return $this->db->update('client_price_rules', ['deleted' => 1]);
    }

    /**
     * Apply client pricing to a project's asset assignments
     * Returns total with client discounts applied
     */
    public function applyClientPricing(int $clientId, int $projectId, int $days): array
    {
        $sql = "SELECT aa.assetsAssignments_id, a.assets_id, at.assetTypes_id,
                       at.assetTypes_name, at.assetTypes_dayRate, at.assetTypes_weekRate,
                       aa.assetsAssignments_customPrice, aa.assetsAssignments_discount
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE aa.projects_id = ? AND aa.assetsAssignments_deleted = 0";
        $assignments = $this->db->rawQuery($sql, [$projectId]) ?: [];

        $items = [];
        $totalStandard = 0;
        $totalDiscounted = 0;

        foreach ($assignments as $a) {
            $pricing = $this->getEffectivePrice($clientId, $a['assetTypes_id'], $days);
            $standardRate = $a['assetTypes_dayRate'];
            $effectiveRate = $pricing['day_rate'] ?? $standardRate;

            if ($pricing['discount_pct'] > 0 && !$pricing['day_rate']) {
                $effectiveRate = $standardRate * (1 - $pricing['discount_pct'] / 100);
            }

            $totalStandard += $standardRate * $days;
            $totalDiscounted += $effectiveRate * $days;

            $items[] = [
                'asset_type' => $a['assetTypes_name'],
                'standard_rate' => $standardRate,
                'effective_rate' => $effectiveRate,
                'discount_pct' => $pricing['discount_pct'],
                'rule_type' => $pricing['type'],
            ];
        }

        return [
            'items' => $items,
            'total_standard' => $totalStandard,
            'total_discounted' => $totalDiscounted,
            'total_savings' => $totalStandard - $totalDiscounted,
        ];
    }
}
