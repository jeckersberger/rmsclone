<?php

/**
 * PricingEngineService - Flexible Preiskalkulations-Engine (L2)
 *
 * Berechnet Preise basierend auf:
 * - Staffelpreise (duration-based pricing tiers)
 * - Mengenrabatte (volume discounts)
 * - Saisonzuschläge (seasonal surcharges)
 * - Paketpreise (bundle pricing)
 * - Kundenspezifische Preislisten (customer-specific pricing)
 */

class PriceResult
{
    public $base_price = 0;
    public $tier_discount = 0;
    public $volume_discount = 0;
    public $seasonal_surcharge = 0;
    public $customer_discount = 0;
    public $final_price = 0;
    public $breakdown = [];
}

class PricingEngineService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate total price for asset(s)
     */
    public function calculatePrice(
        int $assetTypeId,
        int $days,
        int $quantity = 1,
        ?int $clientId = null,
        ?string $startDate = null,
        int $instanceId = 0
    ): PriceResult {
        $result = new PriceResult();

        // 1. Get base price from asset type
        $this->db->where('assetTypes_id', $assetTypeId);
        $assetType = $this->db->getOne('assetTypes');
        if (!$assetType) {
            return $result;
        }
        $basePrice = (float)$assetType['assetTypes_dayRate'];
        $result->base_price = $basePrice * $days * $quantity;

        // 2. Check for customer-specific pricing
        $customerPrice = null;
        if ($clientId) {
            $customerPrice = $this->getCustomerAssetPrice($clientId, $assetTypeId, $instanceId);
            if ($customerPrice !== null) {
                $result->base_price = $customerPrice * $days * $quantity;
                $result->breakdown[] = "Customer-specific price: {$customerPrice} per day";
            }
        }

        // 3. Apply tier pricing (if no customer-specific price)
        if ($customerPrice === null) {
            $tier = $this->getApplicableTier($assetTypeId, $days, $instanceId);
            if ($tier) {
                $tierPrice = (float)$tier['price_per_day'];
                $tierDiscount = $basePrice - $tierPrice;
                $result->tier_discount = $tierDiscount * $days * $quantity;
                $result->base_price = $tierPrice * $days * $quantity;
                $result->breakdown[] = "Tier pricing ({$days} days): {$tierPrice} per day (saved {$tierDiscount} per day)";
            }
        }

        // 4. Apply volume discounts
        $volumeDiscount = $this->getVolumeDiscountPercent($quantity, $instanceId);
        if ($volumeDiscount > 0) {
            $result->volume_discount = $result->base_price * ($volumeDiscount / 100);
            $result->breakdown[] = "Volume discount ({$quantity} units): {$volumeDiscount}%";
        }

        // 5. Apply seasonal surcharge
        if ($startDate) {
            $surcharge = $this->getSeasonalSurchargePercent($startDate, $days, $instanceId);
            if ($surcharge > 0) {
                $result->seasonal_surcharge = ($result->base_price - $result->volume_discount) * ($surcharge / 100);
                $result->breakdown[] = "Seasonal surcharge: {$surcharge}%";
            }
        }

        // 6. Apply customer global discount
        if ($clientId && !$customerPrice) {
            $customerDiscount = $this->getCustomerGlobalDiscount($clientId, $instanceId);
            if ($customerDiscount > 0) {
                $result->customer_discount = ($result->base_price - $result->volume_discount) * ($customerDiscount / 100);
                $result->breakdown[] = "Customer global discount: {$customerDiscount}%";
            }
        }

        // Calculate final price
        $result->final_price = $result->base_price
            - $result->volume_discount
            + $result->seasonal_surcharge
            - $result->customer_discount;

        $result->final_price = max(0, $result->final_price);

        return $result;
    }

    /**
     * Get applicable tier for given days
     */
    public function getApplicableTier(int $assetTypeId, int $days, int $instanceId): ?array
    {
        $sql = "SELECT * FROM pricing_tiers
                WHERE asset_type_id = ?
                AND instances_id = ?
                AND min_days <= ?
                AND (max_days IS NULL OR max_days >= ?)
                ORDER BY min_days DESC
                LIMIT 1";
        $result = $this->db->rawQuery($sql, [$assetTypeId, $instanceId, $days, $days]);
        return $result ? $result[0] : null;
    }

    /**
     * Get all tiers for an asset type
     */
    public function getTiersForAssetType(int $assetTypeId, int $instanceId): array
    {
        $this->db->where('asset_type_id', $assetTypeId);
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('min_days', 'ASC');
        return $this->db->get('pricing_tiers') ?: [];
    }

    /**
     * Save/update tier pricing
     */
    public function saveTier(array $data): int
    {
        $row = [
            'asset_type_id' => intval($data['asset_type_id'] ?? 0),
            'min_days' => intval($data['min_days'] ?? 1),
            'max_days' => !empty($data['max_days']) ? intval($data['max_days']) : null,
            'price_per_day' => floatval($data['price_per_day'] ?? 0),
            'instances_id' => intval($data['instances_id'] ?? 0),
        ];

        if (!empty($data['id'])) {
            // Update existing
            $this->db->where('id', intval($data['id']));
            $this->db->update('pricing_tiers', $row);
            return intval($data['id']);
        }

        return $this->db->insert('pricing_tiers', $row);
    }

    /**
     * Delete a tier
     */
    public function deleteTier(int $id): bool
    {
        $this->db->where('id', $id);
        return (bool)$this->db->delete('pricing_tiers');
    }

    /**
     * Get volume discount percent for quantity
     */
    public function getVolumeDiscountPercent(int $quantity, int $instanceId): float
    {
        $sql = "SELECT discount_percent FROM pricing_volume_discounts
                WHERE instances_id = ?
                AND min_quantity <= ?
                AND (max_quantity IS NULL OR max_quantity >= ?)
                ORDER BY min_quantity DESC
                LIMIT 1";
        $result = $this->db->rawQuery($sql, [$instanceId, $quantity, $quantity]);
        return $result ? floatval($result[0]['discount_percent']) : 0;
    }

    /**
     * Get all volume discounts
     */
    public function getVolumeDiscounts(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('min_quantity', 'ASC');
        return $this->db->get('pricing_volume_discounts') ?: [];
    }

    /**
     * Save/update volume discount
     */
    public function saveVolumeDiscount(array $data): int
    {
        $row = [
            'min_quantity' => intval($data['min_quantity'] ?? 1),
            'max_quantity' => !empty($data['max_quantity']) ? intval($data['max_quantity']) : null,
            'discount_percent' => floatval($data['discount_percent'] ?? 0),
            'instances_id' => intval($data['instances_id'] ?? 0),
        ];

        if (!empty($data['id'])) {
            $this->db->where('id', intval($data['id']));
            $this->db->update('pricing_volume_discounts', $row);
            return intval($data['id']);
        }

        return $this->db->insert('pricing_volume_discounts', $row);
    }

    /**
     * Delete volume discount
     */
    public function deleteVolumeDiscount(int $id): bool
    {
        $this->db->where('id', $id);
        return (bool)$this->db->delete('pricing_volume_discounts');
    }

    /**
     * Get all seasonal surcharges
     */
    public function getSeasonalSurcharges(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('start_month', 'ASC');
        return $this->db->get('pricing_seasonal_surcharges') ?: [];
    }

    /**
     * Get seasonal surcharge percent for date range
     */
    public function getSeasonalSurchargePercent(?string $startDate, int $days, int $instanceId): float
    {
        if (!$startDate) return 0;

        $surcharges = $this->getSeasonalSurcharges($instanceId);
        if (empty($surcharges)) return 0;

        $startTimestamp = strtotime($startDate);
        $endTimestamp = $startTimestamp + ($days * 86400);
        $maxSurcharge = 0;

        foreach ($surcharges as $surcharge) {
            if ($this->dateRangeOverlapsSeason(
                $startTimestamp,
                $endTimestamp,
                (int)$surcharge['start_month'],
                (int)$surcharge['start_day'],
                (int)$surcharge['end_month'],
                (int)$surcharge['end_day']
            )) {
                $maxSurcharge = max($maxSurcharge, floatval($surcharge['surcharge_percent']));
            }
        }

        return $maxSurcharge;
    }

    /**
     * Check if date range overlaps with season
     */
    private function dateRangeOverlapsSeason(
        int $startTs,
        int $endTs,
        int $startMonth,
        int $startDay,
        int $endMonth,
        int $endDay
    ): bool {
        $year = (int)date('Y', $startTs);
        $seasonStart = strtotime("{$year}-{$startMonth}-{$startDay}");
        $seasonEnd = strtotime("{$year}-{$endMonth}-{$endDay}");

        // Handle season spanning year boundary
        if ($seasonEnd < $seasonStart) {
            $seasonEnd = strtotime(($year + 1) . '-' . sprintf('%02d', $endMonth) . '-' . sprintf('%02d', $endDay));
        }

        return !($endTs < $seasonStart || $startTs > $seasonEnd);
    }

    /**
     * Save/update seasonal surcharge
     */
    public function saveSurcharge(array $data): int
    {
        $row = [
            'name' => trim($data['name'] ?? ''),
            'start_month' => intval($data['start_month'] ?? 1),
            'start_day' => intval($data['start_day'] ?? 1),
            'end_month' => intval($data['end_month'] ?? 12),
            'end_day' => intval($data['end_day'] ?? 31),
            'surcharge_percent' => floatval($data['surcharge_percent'] ?? 0),
            'instances_id' => intval($data['instances_id'] ?? 0),
        ];

        if (!empty($data['id'])) {
            $this->db->where('id', intval($data['id']));
            $this->db->update('pricing_seasonal_surcharges', $row);
            return intval($data['id']);
        }

        return $this->db->insert('pricing_seasonal_surcharges', $row);
    }

    /**
     * Delete seasonal surcharge
     */
    public function deleteSurcharge(int $id): bool
    {
        $this->db->where('id', $id);
        return (bool)$this->db->delete('pricing_seasonal_surcharges');
    }

    /**
     * Get all bundles
     */
    public function getBundles(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $this->db->orderBy('name', 'ASC');
        return $this->db->get('pricing_bundles') ?: [];
    }

    /**
     * Get single bundle with items
     */
    public function getBundle(int $bundleId): ?array
    {
        $bundle = $this->db->getOne('pricing_bundles', ['id' => $bundleId]);
        if (!$bundle) return null;

        $this->db->where('bundle_id', $bundleId);
        $bundle['items'] = $this->db->get('pricing_bundle_items') ?: [];

        return $bundle;
    }

    /**
     * Save/update bundle with items
     */
    public function saveBundle(array $bundleData, array $items): int
    {
        $row = [
            'name' => trim($bundleData['name'] ?? ''),
            'description' => trim($bundleData['description'] ?? ''),
            'bundle_price_per_day' => floatval($bundleData['bundle_price_per_day'] ?? 0),
            'instances_id' => intval($bundleData['instances_id'] ?? 0),
            'is_active' => !empty($bundleData['is_active']) ? 1 : 0,
        ];

        if (!empty($bundleData['id'])) {
            // Update existing bundle
            $bundleId = intval($bundleData['id']);
            $this->db->where('id', $bundleId);
            $this->db->update('pricing_bundles', $row);

            // Delete old items
            $this->db->where('bundle_id', $bundleId);
            $this->db->delete('pricing_bundle_items');
        } else {
            // Insert new bundle
            $bundleId = $this->db->insert('pricing_bundles', $row);
        }

        // Insert new items
        foreach ($items as $item) {
            $this->db->insert('pricing_bundle_items', [
                'bundle_id' => $bundleId,
                'asset_type_id' => intval($item['asset_type_id'] ?? 0),
                'quantity' => intval($item['quantity'] ?? 1),
            ]);
        }

        return $bundleId;
    }

    /**
     * Delete bundle
     */
    public function deleteBundle(int $id): bool
    {
        // Items cascade delete via foreign key
        $this->db->where('id', $id);
        return (bool)$this->db->delete('pricing_bundles');
    }

    /**
     * Calculate price for bundle
     */
    public function calculateBundlePrice(
        int $bundleId,
        int $days,
        ?int $clientId = null,
        ?string $startDate = null,
        int $instanceId = 0
    ): PriceResult {
        $bundle = $this->getBundle($bundleId);
        if (!$bundle) {
            return new PriceResult();
        }

        $result = new PriceResult();
        $bundleBasePrice = (float)$bundle['bundle_price_per_day'] * $days;
        $result->base_price = $bundleBasePrice;

        // 2. Apply volume discounts (if multiple bundles ordered)
        $volumeDiscount = $this->getVolumeDiscountPercent(1, $instanceId);
        if ($volumeDiscount > 0) {
            $result->volume_discount = $bundleBasePrice * ($volumeDiscount / 100);
        }

        // 3. Apply seasonal surcharge
        if ($startDate) {
            $surcharge = $this->getSeasonalSurchargePercent($startDate, $days, $instanceId);
            if ($surcharge > 0) {
                $result->seasonal_surcharge = ($bundleBasePrice - $result->volume_discount) * ($surcharge / 100);
            }
        }

        // 4. Apply customer global discount
        if ($clientId) {
            $customerDiscount = $this->getCustomerGlobalDiscount($clientId, $instanceId);
            if ($customerDiscount > 0) {
                $result->customer_discount = ($bundleBasePrice - $result->volume_discount) * ($customerDiscount / 100);
            }
        }

        $result->final_price = $bundleBasePrice
            - $result->volume_discount
            + $result->seasonal_surcharge
            - $result->customer_discount;

        $result->final_price = max(0, $result->final_price);

        return $result;
    }

    /**
     * Get customer-specific price for asset
     */
    public function getCustomerAssetPrice(int $clientId, int $assetTypeId, int $instanceId): ?float
    {
        $list = $this->getCustomerPriceList($clientId, $instanceId);
        if (!$list) return null;

        foreach ($list['items'] as $item) {
            if ($item['asset_type_id'] == $assetTypeId) {
                return floatval($item['custom_price_per_day']);
            }
        }

        return null;
    }

    /**
     * Get customer global discount
     */
    public function getCustomerGlobalDiscount(int $clientId, int $instanceId): float
    {
        $this->db->where('client_id', $clientId);
        $this->db->where('instances_id', $instanceId);
        $list = $this->db->getOne('pricing_customer_lists');

        return $list ? floatval($list['discount_percent'] ?? 0) : 0;
    }

    /**
     * Get customer price list with items
     */
    public function getCustomerPriceList(int $clientId, int $instanceId): ?array
    {
        $this->db->where('client_id', $clientId);
        $this->db->where('instances_id', $instanceId);
        $list = $this->db->getOne('pricing_customer_lists');

        if (!$list) return null;

        $this->db->where('list_id', $list['id']);
        $list['items'] = $this->db->get('pricing_customer_list_items') ?: [];

        return $list;
    }

    /**
     * Save customer price list
     */
    public function saveCustomerPriceList(
        int $clientId,
        array $items,
        ?float $globalDiscount,
        int $instanceId
    ): int {
        // Get or create customer list
        $this->db->where('client_id', $clientId);
        $this->db->where('instances_id', $instanceId);
        $existing = $this->db->getOne('pricing_customer_lists');

        $listData = [
            'client_id' => $clientId,
            'discount_percent' => $globalDiscount ?? 0,
            'instances_id' => $instanceId,
        ];

        if ($existing) {
            $listId = $existing['id'];
            $this->db->where('id', $listId);
            $this->db->update('pricing_customer_lists', $listData);

            // Delete old items
            $this->db->where('list_id', $listId);
            $this->db->delete('pricing_customer_list_items');
        } else {
            $listId = $this->db->insert('pricing_customer_lists', $listData);
        }

        // Insert new items
        foreach ($items as $item) {
            $this->db->insert('pricing_customer_list_items', [
                'list_id' => $listId,
                'asset_type_id' => intval($item['asset_type_id'] ?? 0),
                'custom_price_per_day' => floatval($item['custom_price_per_day'] ?? 0),
            ]);
        }

        return $listId;
    }

    /**
     * Delete customer price list
     */
    public function deleteCustomerPriceList(int $listId): bool
    {
        // Items cascade delete via foreign key
        $this->db->where('id', $listId);
        return (bool)$this->db->delete('pricing_customer_lists');
    }
}
