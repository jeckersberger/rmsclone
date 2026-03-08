<?php

use PHPUnit\Framework\TestCase;

class ProfitCalculationServiceTest extends TestCase
{
    private $db;
    private ProfitCalculationService $service;

    protected function setUp(): void
    {
        $this->db = $this->createMock(MysqliDb::class);
        $this->service = new ProfitCalculationService($this->db);
    }

    public function testCalculateProjectProfitWithAllComponents(): void
    {
        $equipmentRevenue = 5000.00;
        $salesRevenue = 1000.00;
        $staffCost = 2000.00;
        $subHireCost = 500.00;

        // rawQuery for equipment revenue
        $this->db->method('rawQuery')
            ->willReturn([['equipment_revenue' => $equipmentRevenue]]);

        $this->db->method('where')->willReturnSelf();

        // getValue calls: sales, staff, sub-hire
        $this->db->method('getValue')
            ->willReturnOnConsecutiveCalls($salesRevenue, $staffCost, $subHireCost);

        $result = $this->service->calculateProjectProfit(1);

        $this->assertSame($equipmentRevenue, $result['revenue']['equipment']);
        $this->assertSame($salesRevenue, $result['revenue']['sales']);
        $this->assertSame($equipmentRevenue + $salesRevenue, $result['revenue']['total']);
        $this->assertSame($staffCost, $result['costs']['staff']);
        $this->assertSame($subHireCost, $result['costs']['sub_hire']);
        $this->assertSame($staffCost + $subHireCost, $result['costs']['total']);

        $expectedProfit = ($equipmentRevenue + $salesRevenue) - ($staffCost + $subHireCost);
        $this->assertSame($expectedProfit, $result['profit']);
    }

    public function testCalculateProjectProfitMarginCalculation(): void
    {
        $this->db->method('rawQuery')
            ->willReturn([['equipment_revenue' => 10000.00]]);

        $this->db->method('where')->willReturnSelf();

        // sales=0, staff=3000, sub_hire=0
        $this->db->method('getValue')
            ->willReturnOnConsecutiveCalls(0, 3000.00, 0);

        $result = $this->service->calculateProjectProfit(1);

        // profit = 10000 - 3000 = 7000
        // margin = (7000 / 10000) * 100 = 70.0
        $this->assertSame(7000.0, $result['profit']);
        $this->assertSame(70.0, $result['margin_pct']);
    }

    public function testCalculateProjectProfitMarginIsZeroWhenNoRevenue(): void
    {
        $this->db->method('rawQuery')
            ->willReturn([['equipment_revenue' => 0]]);

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getValue')->willReturn(0);

        $result = $this->service->calculateProjectProfit(1);

        $this->assertSame(0.0, $result['margin_pct']);
    }

    public function testCalculateProjectProfitNegativeMargin(): void
    {
        $this->db->method('rawQuery')
            ->willReturn([['equipment_revenue' => 1000.00]]);

        $this->db->method('where')->willReturnSelf();

        // sales=0, staff=2000, sub_hire=500
        $this->db->method('getValue')
            ->willReturnOnConsecutiveCalls(0, 2000.00, 500.00);

        $result = $this->service->calculateProjectProfit(1);

        // profit = 1000 - 2500 = -1500
        // margin = (-1500/1000)*100 = -150.0
        $this->assertSame(-1500.0, $result['profit']);
        $this->assertSame(-150.0, $result['margin_pct']);
    }

    public function testCalculatePeriodProfitAggregatesMultipleProjects(): void
    {
        $projects = [
            ['projects_id' => 1, 'projects_name' => 'Project A'],
            ['projects_id' => 2, 'projects_name' => 'Project B'],
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('get')->willReturn($projects);

        // rawQuery for equipment revenue (called for each project)
        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                [['equipment_revenue' => 3000.00]],
                [['equipment_revenue' => 7000.00]]
            );

        // getValue: for each project: sales, staff, sub_hire
        $this->db->method('getValue')
            ->willReturnOnConsecutiveCalls(
                0, 1000.00, 0,      // Project A: sales=0, staff=1000, sub=0
                0, 2000.00, 0       // Project B: sales=0, staff=2000, sub=0
            );

        $result = $this->service->calculatePeriodProfit(1, '2026-01-01', '2026-03-31');

        $this->assertSame(10000.0, $result['totals']['revenue']);
        $this->assertSame(3000.0, $result['totals']['costs']);
        $this->assertSame(7000.0, $result['totals']['profit']);
        $this->assertSame(70.0, $result['totals']['margin_pct']);
        $this->assertCount(2, $result['projects']);
    }

    public function testCalculatePeriodProfitSortsByProfitDescending(): void
    {
        $projects = [
            ['projects_id' => 1, 'projects_name' => 'Low Profit'],
            ['projects_id' => 2, 'projects_name' => 'High Profit'],
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('get')->willReturn($projects);

        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(
                [['equipment_revenue' => 1000.00]],
                [['equipment_revenue' => 5000.00]]
            );

        $this->db->method('getValue')->willReturn(0);

        $result = $this->service->calculatePeriodProfit(1, '2026-01-01', '2026-03-31');

        // High Profit project (5000) should come first
        $this->assertSame('High Profit', $result['projects'][0]['projects_name']);
        $this->assertSame('Low Profit', $result['projects'][1]['projects_name']);
    }

    public function testCalculatePeriodProfitHandlesNoProjects(): void
    {
        $this->db->method('where')->willReturnSelf();
        $this->db->method('get')->willReturn(null);

        $result = $this->service->calculatePeriodProfit(1, '2026-01-01', '2026-03-31');

        $this->assertSame(0, $result['totals']['revenue']);
        $this->assertSame(0, $result['totals']['costs']);
        $this->assertSame(0, $result['totals']['profit']);
        $this->assertSame(0.0, $result['totals']['margin_pct']);
        $this->assertCount(0, $result['projects']);
    }

    public function testGetTopClientsRoundsRevenue(): void
    {
        $rows = [
            ['clients_id' => 1, 'clients_name' => 'Acme', 'project_count' => 3, 'total_revenue' => 9999.999, 'paid_revenue' => 5000.005],
        ];

        $this->db->method('rawQuery')->willReturn($rows);

        $result = $this->service->getTopClients(1, 2026, 10);

        $this->assertCount(1, $result);
        $this->assertSame(10000.0, $result[0]['total_revenue']);
        $this->assertSame(5000.01, $result[0]['paid_revenue']);
    }

    public function testGetTopClientsReturnsEmptyForNoData(): void
    {
        $this->db->method('rawQuery')->willReturn([]);

        $result = $this->service->getTopClients(1, 2026, 10);

        $this->assertSame([], $result);
    }
}
