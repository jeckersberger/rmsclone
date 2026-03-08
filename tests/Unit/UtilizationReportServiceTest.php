<?php

use PHPUnit\Framework\TestCase;

class UtilizationReportServiceTest extends TestCase
{
    private $db;
    private UtilizationReportService $service;

    protected function setUp(): void
    {
        $this->db = $this->createMock(MysqliDb::class);
        $this->service = new UtilizationReportService($this->db);
    }

    public function testCalculateUtilizationReturnsZeroForNoAssets(): void
    {
        // rawQuery: asset count = 0
        $this->db->method('rawQuery')
            ->willReturn([['cnt' => 0]]);

        $result = $this->service->calculateUtilization(1, 10, 2026, 3);

        $this->assertSame(10, $result['assetTypes_id']);
        $this->assertSame(2026, $result['year']);
        $this->assertSame(3, $result['month']);
        $this->assertSame(0, $result['days_rented']);
        $this->assertSame(0, $result['days_available']);
        $this->assertSame(0, $result['revenue']);
        $this->assertSame(0, $result['utilization_pct']);
    }

    public function testCalculateUtilizationPercentage(): void
    {
        $assetCount = 5;
        $daysInMarch = 31;
        $daysAvailable = $assetCount * $daysInMarch; // 155
        $daysRented = 80;
        $revenue = 12000.00;

        // rawQuery calls: 1) asset count, 2) rental days, 3) revenue, 4) cache update
        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                [['cnt' => $assetCount]],
                [['rental_days' => $daysRented]],
                [['revenue' => $revenue]],
                true // cache update
            );

        $result = $this->service->calculateUtilization(1, 10, 2026, 3);

        $this->assertSame($daysAvailable, $result['days_available']);
        $this->assertSame($daysRented, $result['days_rented']);
        $this->assertSame($revenue, $result['revenue']);

        $expectedPct = round(($daysRented / $daysAvailable) * 100, 2);
        $this->assertSame($expectedPct, $result['utilization_pct']);
    }

    public function testCalculateUtilizationCapsRentedDaysAtAvailable(): void
    {
        $assetCount = 2;
        $daysInJan = 31;
        $daysAvailable = $assetCount * $daysInJan; // 62

        // rental_days exceeds available (e.g., overlapping assignments)
        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                [['cnt' => $assetCount]],
                [['rental_days' => 100]], // more than 62
                [['revenue' => 5000.00]],
                true
            );

        $result = $this->service->calculateUtilization(1, 10, 2026, 1);

        // Should be capped at daysAvailable
        $this->assertSame($daysAvailable, $result['days_rented']);
        $this->assertSame(100.0, $result['utilization_pct']);
    }

    public function testCalculateUtilizationHandlesNegativeRentalDays(): void
    {
        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                [['cnt' => 3]],
                [['rental_days' => -5]], // negative from DATEDIFF edge case
                [['revenue' => 0]],
                true
            );

        $result = $this->service->calculateUtilization(1, 10, 2026, 6);

        $this->assertSame(0, $result['days_rented']);
        $this->assertSame(0.0, $result['utilization_pct']);
    }

    public function testCalculateRoiForProfitableAsset(): void
    {
        $assetTypeData = [
            'assetTypes_id' => 5,
            'assetTypes_name' => 'Sony FX6',
            'assetTypes_value' => 6000.00,
            'assetTypes_dayRate' => 250.00,
            'assetCategories_name' => 'Cameras',
            'asset_count' => 2,
        ];

        $revenueData = [
            'total_revenue' => 18000.00,
            'total_assignments' => 45,
            'first_rental' => '2024-01-15',
            'last_rental' => '2026-01-15',
        ];

        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                [$assetTypeData],
                [$revenueData]
            );

        $result = $this->service->calculateRoi(5);

        $purchasePrice = 6000.00 * 2; // 12000
        $this->assertSame(5, $result['assetTypes_id']);
        $this->assertSame('Sony FX6', $result['assetTypes_name']);
        $this->assertSame(2, $result['asset_count']);
        $this->assertSame($purchasePrice, $result['purchase_price']);
        $this->assertSame(250.00, $result['day_rate']);
        $this->assertSame(18000.00, $result['total_revenue']);
        $this->assertSame(45, $result['total_assignments']);

        // ROI = ((18000 - 12000) / 12000) * 100 = 50%
        $expectedRoi = round(((18000 - $purchasePrice) / $purchasePrice) * 100, 2);
        $this->assertSame($expectedRoi, $result['roi_pct']);
        $this->assertTrue($result['is_profitable']);
        $this->assertNotNull($result['payback_months']);
    }

    public function testCalculateRoiForUnprofitableAsset(): void
    {
        $assetTypeData = [
            'assetTypes_id' => 7,
            'assetTypes_name' => 'Expensive Rig',
            'assetTypes_value' => 50000.00,
            'assetTypes_dayRate' => 100.00,
            'assetCategories_name' => 'Rigs',
            'asset_count' => 1,
        ];

        $revenueData = [
            'total_revenue' => 5000.00,
            'total_assignments' => 10,
            'first_rental' => '2025-06-01',
            'last_rental' => '2025-12-01',
        ];

        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                [$assetTypeData],
                [$revenueData]
            );

        $result = $this->service->calculateRoi(7);

        $this->assertFalse($result['is_profitable']);
        $this->assertLessThan(0, $result['roi_pct']);
    }

    public function testCalculateRoiReturnsErrorForMissingAssetType(): void
    {
        $this->db->method('rawQuery')->willReturn(null);

        $result = $this->service->calculateRoi(999);

        $this->assertArrayHasKey('error', $result);
    }

    public function testCalculateRoiHandlesZeroPurchasePrice(): void
    {
        $assetTypeData = [
            'assetTypes_id' => 8,
            'assetTypes_name' => 'Donated Gear',
            'assetTypes_value' => 0,
            'assetTypes_dayRate' => 50.00,
            'assetCategories_name' => 'Misc',
            'asset_count' => 1,
        ];

        $revenueData = [
            'total_revenue' => 3000.00,
            'total_assignments' => 20,
            'first_rental' => '2025-01-01',
            'last_rental' => '2025-12-01',
        ];

        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                [$assetTypeData],
                [$revenueData]
            );

        $result = $this->service->calculateRoi(8);

        // ROI with 0 purchase price should be 0 (division guard)
        $this->assertSame(0.0, $result['roi_pct']);
        $this->assertTrue($result['is_profitable']); // 3000 >= 0
        $this->assertSame(0.0, $result['payback_months']); // payback is instant
    }

    public function testGetUtilizationOverviewSortsByUtilizationDesc(): void
    {
        $assetTypes = [
            ['assetTypes_id' => 1, 'assetTypes_name' => 'Low Use', 'assetCategories_name' => 'Cat A'],
            ['assetTypes_id' => 2, 'assetTypes_name' => 'High Use', 'assetCategories_name' => 'Cat B'],
        ];

        // rawQuery calls: 1) asset types list, then per asset type: count, rental, revenue, cache
        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                $assetTypes,
                // Asset type 1: low utilization
                [['cnt' => 2]], [['rental_days' => 5]], [['revenue' => 500]], true,
                // Asset type 2: high utilization
                [['cnt' => 2]], [['rental_days' => 50]], [['revenue' => 5000]], true
            );

        $result = $this->service->getUtilizationOverview(1, 2026, 3);

        $this->assertCount(2, $result);
        // High Use should come first (sorted by utilization desc)
        $this->assertSame('High Use', $result[0]['assetTypes_name']);
        $this->assertSame('Low Use', $result[1]['assetTypes_name']);
    }
}
