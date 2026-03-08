<?php

use PHPUnit\Framework\TestCase;

class SequenceServiceTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        $this->db = $this->createMock(MysqliDb::class);
    }

    public function testNextGeneratesFormattedInvoiceNumber(): void
    {
        $instanceId = 1;
        $type = 'invoice';
        $userId = 42;
        $year = date('Y');

        $seqRow = [
            'id' => 10,
            'instances_id' => $instanceId,
            'type' => $type,
            'prefix' => "RE-{$year}-",
            'padding' => 4,
            'suffix' => '',
            'current_number' => 5,
            'reset_period' => 'yearly',
            'last_reset_at' => date('Y-m-d H:i:s'),
        ];

        // startTransaction
        $this->db->expects($this->once())->method('startTransaction');

        // rawQuery for SELECT ... FOR UPDATE returns existing sequence
        $this->db->expects($this->once())
            ->method('rawQuery')
            ->willReturn([$seqRow]);

        // where calls for update and insert
        $this->db->method('where')->willReturnSelf();

        // update current_number
        $this->db->expects($this->once())
            ->method('update')
            ->with('document_sequences', ['current_number' => 6]);

        // insert into document_sequence_log
        $this->db->expects($this->once())
            ->method('insert')
            ->with('document_sequence_log', $this->callback(function ($data) use ($year) {
                return $data['doc_number'] === "RE-{$year}-0006"
                    && $data['sequence_value'] === 6
                    && $data['used'] === true;
            }));

        // commit
        $this->db->expects($this->once())->method('commit');

        $result = SequenceService::next($this->db, $instanceId, $type, $userId);

        $this->assertSame("RE-{$year}-0006", $result);
    }

    public function testNextGeneratesQuoteNumber(): void
    {
        $instanceId = 1;
        $type = 'quote';
        $year = date('Y');

        $seqRow = [
            'id' => 11,
            'instances_id' => $instanceId,
            'type' => $type,
            'prefix' => "AN-{$year}-",
            'padding' => 4,
            'suffix' => '',
            'current_number' => 0,
            'reset_period' => 'yearly',
            'last_reset_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->method('startTransaction');
        $this->db->method('rawQuery')->willReturn([$seqRow]);
        $this->db->method('where')->willReturnSelf();
        $this->db->method('update');
        $this->db->method('insert');
        $this->db->method('commit');

        $result = SequenceService::next($this->db, $instanceId, $type);

        $this->assertSame("AN-{$year}-0001", $result);
    }

    public function testNextGeneratesDeliveryNoteNumber(): void
    {
        $instanceId = 1;
        $type = 'delivery_note';
        $year = date('Y');

        $seqRow = [
            'id' => 12,
            'instances_id' => $instanceId,
            'type' => $type,
            'prefix' => "LS-{$year}-",
            'padding' => 4,
            'suffix' => '',
            'current_number' => 99,
            'reset_period' => 'yearly',
            'last_reset_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->method('startTransaction');
        $this->db->method('rawQuery')->willReturn([$seqRow]);
        $this->db->method('where')->willReturnSelf();
        $this->db->method('update');
        $this->db->method('insert');
        $this->db->method('commit');

        $result = SequenceService::next($this->db, $instanceId, $type);

        $this->assertSame("LS-{$year}-0100", $result);
    }

    public function testNextPadsNumberCorrectly(): void
    {
        $instanceId = 1;
        $type = 'invoice';
        $year = date('Y');

        $seqRow = [
            'id' => 10,
            'instances_id' => $instanceId,
            'type' => $type,
            'prefix' => "RE-{$year}-",
            'padding' => 6,
            'suffix' => '/X',
            'current_number' => 41,
            'reset_period' => 'yearly',
            'last_reset_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->method('startTransaction');
        $this->db->method('rawQuery')->willReturn([$seqRow]);
        $this->db->method('where')->willReturnSelf();
        $this->db->method('update');
        $this->db->method('insert');
        $this->db->method('commit');

        $result = SequenceService::next($this->db, $instanceId, $type);

        $this->assertSame("RE-{$year}-000042/X", $result);
    }

    public function testNextCreatesDefaultSequenceWhenNoneExists(): void
    {
        $instanceId = 1;
        $type = 'invoice';
        $year = date('Y');

        $seqRow = [
            'id' => 1,
            'instances_id' => $instanceId,
            'type' => $type,
            'prefix' => "RE-{$year}-",
            'padding' => 4,
            'suffix' => '',
            'current_number' => 0,
            'reset_period' => 'yearly',
            'last_reset_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->method('startTransaction');

        // First rawQuery returns null (no sequence), second returns the newly created one
        $this->db->method('rawQuery')
            ->willReturnOnConsecutiveCalls(null, [$seqRow]);

        $this->db->method('where')->willReturnSelf();
        $this->db->method('update');
        $this->db->method('commit');

        // insert called twice: once for document_sequences, once for document_sequence_log
        $this->db->expects($this->exactly(2))->method('insert');

        $result = SequenceService::next($this->db, $instanceId, $type);

        $this->assertSame("RE-{$year}-0001", $result);
    }

    public function testNextRollsBackOnException(): void
    {
        $this->db->method('startTransaction');
        $this->db->method('rawQuery')->willThrowException(new \RuntimeException('DB error'));
        $this->db->expects($this->once())->method('rollback');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        SequenceService::next($this->db, 1, 'invoice');
    }

    public function testVoidNumberMarksAsUnused(): void
    {
        $this->db->expects($this->exactly(2))
            ->method('where')
            ->willReturnSelf();

        $this->db->expects($this->once())
            ->method('update')
            ->with('document_sequence_log', $this->callback(function ($data) {
                return $data['used'] === false && $data['void_reason'] === 'Storniert';
            }))
            ->willReturn(true);

        $result = SequenceService::voidNumber($this->db, 1, 'RE-2026-0001', 'Storniert');

        $this->assertTrue($result);
    }

    public function testFindGapsDetectsMissingNumbers(): void
    {
        // Sequence values: 1, 2, 5, 8 => gaps: 3, 4, 6, 7
        $rows = [
            ['sequence_value' => 1],
            ['sequence_value' => 2],
            ['sequence_value' => 5],
            ['sequence_value' => 8],
        ];

        $this->db->method('rawQuery')->willReturn($rows);

        $gaps = SequenceService::findGaps($this->db, 1, 'invoice');

        $this->assertSame([3, 4, 6, 7], $gaps);
    }

    public function testFindGapsReturnsEmptyForContiguousSequence(): void
    {
        $rows = [
            ['sequence_value' => 1],
            ['sequence_value' => 2],
            ['sequence_value' => 3],
        ];

        $this->db->method('rawQuery')->willReturn($rows);

        $gaps = SequenceService::findGaps($this->db, 1, 'invoice');

        $this->assertSame([], $gaps);
    }

    public function testFindGapsReturnsEmptyForEmptySequence(): void
    {
        $this->db->method('rawQuery')->willReturn([]);

        $gaps = SequenceService::findGaps($this->db, 1, 'invoice');

        $this->assertSame([], $gaps);
    }
}
