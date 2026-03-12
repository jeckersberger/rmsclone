<?php

use PHPUnit\Framework\TestCase;

class DocumentLifecycleServiceTest extends TestCase
{
    private $db;
    private DocumentLifecycleService $service;

    protected function setUp(): void
    {
        $this->db = $this->createMock(MysqliDb::class);
        $this->service = new DocumentLifecycleService($this->db);
    }

    // ── Status Transitions ──────────────────────────────────────────

    public function testChangeStatusFromDraftToSentForInvoice(): void
    {
        $doc = [
            'id' => 1,
            'doc_type' => 'invoice',
            'status' => 'draft',
            'instances_id' => 1,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $result = $this->service->changeStatus(1, 'sent', 42, 'Sending invoice');

        $this->assertTrue($result);
    }

    public function testChangeStatusRejectInvalidTransition(): void
    {
        $doc = [
            'id' => 1,
            'doc_type' => 'invoice',
            'status' => 'draft',
            'instances_id' => 1,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);

        // draft -> paid is not allowed for invoice (must go through sent)
        $result = $this->service->changeStatus(1, 'paid', 42);

        $this->assertFalse($result);
    }

    public function testChangeStatusReturnsFalseForMissingDoc(): void
    {
        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn(null);

        $result = $this->service->changeStatus(999, 'sent', 42);

        $this->assertFalse($result);
    }

    public function testChangeStatusFromSentToPaidForInvoice(): void
    {
        $doc = [
            'id' => 1,
            'doc_type' => 'invoice',
            'status' => 'sent',
            'instances_id' => 1,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $result = $this->service->changeStatus(1, 'paid', 42);

        $this->assertTrue($result);
    }

    public function testChangeStatusFromSentToOverdueForInvoice(): void
    {
        $doc = [
            'id' => 1,
            'doc_type' => 'invoice',
            'status' => 'sent',
            'instances_id' => 1,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $result = $this->service->changeStatus(1, 'overdue', 42);

        $this->assertTrue($result);
    }

    public function testChangeStatusCannotTransitionFromPaid(): void
    {
        $doc = [
            'id' => 1,
            'doc_type' => 'invoice',
            'status' => 'paid',
            'instances_id' => 1,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);

        // paid is a terminal state for invoices
        $result = $this->service->changeStatus(1, 'sent', 42);

        $this->assertFalse($result);
    }

    public function testChangeStatusCannotTransitionFromCancelled(): void
    {
        $doc = [
            'id' => 1,
            'doc_type' => 'invoice',
            'status' => 'cancelled',
            'instances_id' => 1,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);

        $result = $this->service->changeStatus(1, 'sent', 42);

        $this->assertFalse($result);
    }

    // ── Quote Transitions ───────────────────────────────────────────

    public function testQuoteDraftToSent(): void
    {
        $doc = ['id' => 2, 'doc_type' => 'quote', 'status' => 'draft', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(2, 'sent', 42));
    }

    public function testQuoteSentToAccepted(): void
    {
        $doc = ['id' => 2, 'doc_type' => 'quote', 'status' => 'sent', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(2, 'accepted', 42));
    }

    public function testQuoteSentToRejected(): void
    {
        $doc = ['id' => 2, 'doc_type' => 'quote', 'status' => 'sent', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(2, 'rejected', 42));
    }

    public function testQuoteAcceptedCannotTransition(): void
    {
        $doc = ['id' => 2, 'doc_type' => 'quote', 'status' => 'accepted', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);

        $this->assertFalse($this->service->changeStatus(2, 'sent', 42));
    }

    // ── Invoice reminded/overdue cycle ──────────────────────────────

    public function testInvoiceOverdueToReminded(): void
    {
        $doc = ['id' => 3, 'doc_type' => 'invoice', 'status' => 'overdue', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(3, 'reminded', 42));
    }

    public function testInvoiceRemindedToOverdue(): void
    {
        $doc = ['id' => 3, 'doc_type' => 'invoice', 'status' => 'reminded', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(3, 'overdue', 42));
    }

    public function testInvoiceRemindedToPaid(): void
    {
        $doc = ['id' => 3, 'doc_type' => 'invoice', 'status' => 'reminded', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(3, 'paid', 42));
    }

    // ── Credit Note and Cancellation ────────────────────────────────

    public function testCreditNoteDraftToSent(): void
    {
        $doc = ['id' => 4, 'doc_type' => 'credit_note', 'status' => 'draft', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(4, 'sent', 42));
    }

    public function testCreditNoteSentIsTerminal(): void
    {
        $doc = ['id' => 4, 'doc_type' => 'credit_note', 'status' => 'sent', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);

        $this->assertFalse($this->service->changeStatus(4, 'paid', 42));
    }

    // ── Create ──────────────────────────────────────────────────────

    public function testCreateReturnsDocId(): void
    {
        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn(['valid_until_default_days' => 30]);
        $this->db->method('insert');
        $this->db->method('getInsertId')->willReturn(42);

        $docId = $this->service->create(1, 10, 'quote', [
            'doc_number' => 'AN-2026-0001',
            'net_amount' => 1000,
            'gross_amount' => 1190,
            'created_by' => 1,
        ]);

        $this->assertSame(42, $docId);
    }

    public function testCreateSetsDefaultValidUntilForQuote(): void
    {
        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn(['valid_until_default_days' => 14]);

        $this->db->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(function ($table, $data) {
                if ($table === 'document_lifecycle') {
                    // valid_until should be set to +14 days
                    $expected = date('Y-m-d', strtotime('+14 days'));
                    $this->assertSame($expected, $data['valid_until']);
                    $this->assertSame('draft', $data['status']);
                }
            });

        $this->db->method('getInsertId')->willReturn(1);

        $this->service->create(1, 10, 'quote', [
            'doc_number' => 'AN-2026-0001',
            'created_by' => 1,
        ]);
    }

    // ── Mark Paid ───────────────────────────────────────────────────

    public function testMarkPaidUpdatesInvoice(): void
    {
        $doc = [
            'id' => 5,
            'doc_type' => 'invoice',
            'status' => 'sent',
            'instances_id' => 1,
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $result = $this->service->markPaid(5, 1190.00, 42, 'SEPA-REF-123');

        $this->assertTrue($result);
    }

    public function testMarkPaidReturnsFalseForNonInvoice(): void
    {
        $doc = [
            'id' => 5,
            'doc_type' => 'quote',
            'status' => 'sent',
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);

        $result = $this->service->markPaid(5, 1000, 42);

        $this->assertFalse($result);
    }

    public function testMarkPaidReturnsFalseForMissingDoc(): void
    {
        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn(null);

        $result = $this->service->markPaid(999, 1000, 42);

        $this->assertFalse($result);
    }

    // ── Partial Invoice Transitions ─────────────────────────────────

    public function testPartialInvoiceDraftToSent(): void
    {
        $doc = ['id' => 6, 'doc_type' => 'partial_invoice', 'status' => 'draft', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(6, 'sent', 42));
    }

    public function testPartialInvoiceSentToPaid(): void
    {
        $doc = ['id' => 6, 'doc_type' => 'partial_invoice', 'status' => 'sent', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);
        $this->db->method('update')->willReturn(true);
        $this->db->method('insert');

        $this->assertTrue($this->service->changeStatus(6, 'paid', 42));
    }

    // ── Unknown doc_type / status ───────────────────────────────────

    public function testChangeStatusRejectsUnknownDocType(): void
    {
        $doc = ['id' => 7, 'doc_type' => 'unknown_type', 'status' => 'draft', 'instances_id' => 1];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('getOne')->willReturn($doc);

        $result = $this->service->changeStatus(7, 'sent', 42);

        $this->assertFalse($result);
    }

    // ── Get Project Documents ───────────────────────────────────────

    public function testGetProjectDocumentsReturnsDocuments(): void
    {
        $docs = [
            ['id' => 1, 'doc_type' => 'invoice', 'status' => 'paid'],
            ['id' => 2, 'doc_type' => 'quote', 'status' => 'sent'],
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();
        $this->db->method('get')->willReturn($docs);

        $result = $this->service->getProjectDocuments(1, 10);

        $this->assertCount(2, $result);
    }

    public function testGetProjectDocumentsReturnsEmptyArray(): void
    {
        $this->db->method('where')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();
        $this->db->method('get')->willReturn(false);

        $result = $this->service->getProjectDocuments(1, 10);

        $this->assertSame([], $result);
    }

    // ── Get Overdue Invoices ────────────────────────────────────────

    public function testGetOverdueInvoicesReturnsMatchingDocs(): void
    {
        $docs = [
            ['id' => 1, 'doc_type' => 'invoice', 'status' => 'sent', 'due_date' => '2025-01-01'],
        ];

        $this->db->method('where')->willReturnSelf();
        $this->db->method('orderBy')->willReturnSelf();
        $this->db->method('get')->willReturn($docs);

        $result = $this->service->getOverdueInvoices(1);

        $this->assertCount(1, $result);
    }
}
