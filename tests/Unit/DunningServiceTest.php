<?php

use PHPUnit\Framework\TestCase;

class DunningServiceTest extends TestCase
{
    private $db;
    private DunningService $service;

    protected function setUp(): void
    {
        $this->db = $this->createMock(MysqliDb::class);
        $this->service = new DunningService($this->db);
    }

    public function testGetDunningLevelsReturnsOrderedLevels(): void
    {
        $levels = [
            ['id' => 1, 'level' => 0, 'name' => 'Zahlungserinnerung', 'days_after_due' => 7, 'fee' => 0, 'interest_rate' => 0],
            ['id' => 2, 'level' => 1, 'name' => '1. Mahnung', 'days_after_due' => 21, 'fee' => 0, 'interest_rate' => 0],
            ['id' => 3, 'level' => 2, 'name' => '2. Mahnung', 'days_after_due' => 35, 'fee' => 5.00, 'interest_rate' => 0],
            ['id' => 4, 'level' => 3, 'name' => 'Letzte Mahnung', 'days_after_due' => 49, 'fee' => 10.00, 'interest_rate' => 5],
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();
        $this->db->method('get')->willReturn($levels);

        $result = $this->service->getDunningLevels(1);

        $this->assertCount(4, $result);
        $this->assertSame(0, $result[0]['level']);
        $this->assertSame(3, $result[3]['level']);
    }

    public function testGetDunningLevelsReturnsEmptyArrayWhenNoneExist(): void
    {
        $this->db->method('where')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();
        $this->db->method('get')->willReturn(false);

        $result = $this->service->getDunningLevels(1);

        $this->assertSame([], $result);
    }

    public function testGetOverdueInvoicesCalculatesDaysOverdue(): void
    {
        $dueDate = date('Y-m-d', strtotime('-10 days'));
        $invoices = [
            [
                'id' => 100,
                'instances_id' => 1,
                'doc_type' => 'invoice',
                'status' => 'sent',
                'due_date' => $dueDate,
                'gross_amount' => 1000.00,
            ],
        ];

        // The method calls multiple where/join/orderBy/get, then getLastDunning and getNextDunningLevel internally
        $this->db->method('where')->willReturnSelf();
        $this->db->method('join')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();

        // get is called once for overdue invoices, once in getNextDunningLevel (none)
        $this->db->method('get')
            ->willReturnOnConsecutiveCalls($invoices);

        // getOne called by getLastDunning (no previous dunning) and getNextDunningLevel
        $this->db->method('getOne')
            ->willReturnOnConsecutiveCalls(
                null,  // getLastDunning: no previous dunning
                null   // getNextDunningLevel: no next level
            );

        $result = $this->service->getOverdueInvoices(1);

        $this->assertCount(1, $result);
        $this->assertSame(10, $result[0]['days_overdue']);
    }

    public function testCreateDunningReturnsNullForNonExistentInvoice(): void
    {
        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn(null);

        $result = $this->service->createDunning(1, 999, 42);

        $this->assertNull($result);
    }

    public function testCreateDunningReturnsNullWhenPaused(): void
    {
        $invoice = [
            'id' => 100,
            'due_date' => date('Y-m-d', strtotime('-30 days')),
            'gross_amount' => 1000.00,
        ];

        $lastDunning = [
            'dunning_level' => 1,
            'dunning_history_paused' => true,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();

        // getOne: first for invoice, second for getLastDunning
        $this->db->method('getOne')
            ->willReturnOnConsecutiveCalls($invoice, $lastDunning);

        $result = $this->service->createDunning(1, 100, 42);

        $this->assertNull($result);
    }

    public function testCreateDunningCalculatesInterestForLevel3(): void
    {
        $daysOverdue = 50;
        $dueDate = date('Y-m-d', strtotime("-{$daysOverdue} days"));
        $grossAmount = 1000.00;

        $invoice = [
            'id' => 100,
            'due_date' => $dueDate,
            'gross_amount' => $grossAmount,
        ];

        $lastDunning = [
            'dunning_level' => 2,
            'dunning_history_paused' => false,
        ];

        $nextLevel = [
            'id' => 4,
            'level' => 3,
            'name' => 'Letzte Mahnung',
            'days_after_due' => 49,
            'fee' => 10.00,
            'interest_rate' => 5.0,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();

        // getOne calls: invoice, getLastDunning, getNextDunningLevel, then in changeStatus
        $this->db->method('getOne')
            ->willReturnOnConsecutiveCalls(
                $invoice,      // createDunning: load invoice
                $lastDunning,  // getLastDunning
                $nextLevel,    // getNextDunningLevel
                // changeStatus will also call getOne, but we use willReturn for further calls
                ['id' => 100, 'doc_type' => 'invoice', 'status' => 'sent', 'instances_id' => 1]
            );

        $this->db->method('getInsertId')->willReturn(55);
        $this->db->method('insert');
        $this->db->method('update');

        $result = $this->service->createDunning(1, 100, 42);

        $this->assertNotNull($result);
        $this->assertSame(3, $result['level']);
        $this->assertSame('Letzte Mahnung', $result['level_name']);
        $this->assertSame(10.00, $result['fee']);
        // interest = 1000 * 5/100 / 365 * daysOverdue
        $expectedInterest = round($grossAmount * (5.0 / 100) / 365 * $daysOverdue, 2);
        $this->assertSame($expectedInterest, $result['interest']);
        $this->assertSame($grossAmount + 10.00 + $expectedInterest, $result['total_due']);
    }

    public function testGetDunningHistoryReturnsChronologicalEntries(): void
    {
        $history = [
            ['dunning_level' => 0, 'dunning_date' => '2026-01-15', 'level_name' => 'Zahlungserinnerung'],
            ['dunning_level' => 1, 'dunning_date' => '2026-01-29', 'level_name' => '1. Mahnung'],
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('join')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();
        $this->db->method('get')->willReturn($history);

        $result = $this->service->getDunningHistory(100);

        $this->assertCount(2, $result);
        $this->assertSame('Zahlungserinnerung', $result[0]['level_name']);
        $this->assertSame('1. Mahnung', $result[1]['level_name']);
    }

    public function testGetDashboardStatsCalculatesTotals(): void
    {
        $dueDate1 = date('Y-m-d', strtotime('-10 days'));
        $dueDate2 = date('Y-m-d', strtotime('-25 days'));

        $invoices = [
            [
                'id' => 100,
                'due_date' => $dueDate1,
                'gross_amount' => 500.00,
            ],
            [
                'id' => 101,
                'due_date' => $dueDate2,
                'gross_amount' => 1500.00,
            ],
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('join')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();

        // get for getOverdueInvoices
        $this->db->method('get')
            ->willReturn($invoices);

        // getOne calls for getLastDunning (null each) and getNextDunningLevel
        $nextLevel0 = ['id' => 1, 'level' => 0, 'days_after_due' => 7, 'fee' => 0, 'interest_rate' => 0];
        $nextLevel1 = ['id' => 2, 'level' => 1, 'days_after_due' => 21, 'fee' => 0, 'interest_rate' => 0];

        $this->db->method('getOne')
            ->willReturnOnConsecutiveCalls(
                null, $nextLevel0,   // invoice 100: no last dunning, next level 0 (10 days > 7)
                null, $nextLevel1    // invoice 101: no last dunning, next level 1 (25 days > 21)
            );

        $result = $this->service->getDashboardStats(1);

        $this->assertSame(2, $result['total_overdue_count']);
        $this->assertSame(2000.00, $result['total_overdue_amount']);
        $this->assertArrayHasKey('by_level', $result);
    }
}
