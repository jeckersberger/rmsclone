<?php

use PHPUnit\Framework\TestCase;

class ReportExportServiceTest extends TestCase
{
    private ReportExportService $service;

    protected function setUp(): void
    {
        $this->service = new ReportExportService();
    }

    public function testExportCsvReturnsBase64EncodedData(): void
    {
        $headers = ['Name', 'Amount'];
        $rows = [['Widget', '100.00']];

        $result = $this->service->exportCsv($headers, $rows, 'test_export');

        $this->assertArrayHasKey('csv', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('row_count', $result);

        // Verify it is valid base64
        $decoded = base64_decode($result['csv'], true);
        $this->assertNotFalse($decoded);
    }

    public function testExportCsvFilenameHasCsvExtension(): void
    {
        $result = $this->service->exportCsv(['H'], [['V']], 'my_report');

        $this->assertSame('my_report.csv', $result['filename']);
    }

    public function testExportCsvRowCountMatchesInput(): void
    {
        $rows = [['A', '1'], ['B', '2'], ['C', '3']];
        $result = $this->service->exportCsv(['Col1', 'Col2'], $rows, 'test');

        $this->assertSame(3, $result['row_count']);
    }

    public function testExportCsvContainsUtf8Bom(): void
    {
        $result = $this->service->exportCsv(['H'], [['V']], 'test');
        $decoded = base64_decode($result['csv']);

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $this->assertStringStartsWith($bom, $decoded);
    }

    public function testExportCsvUsesSemicolonSeparator(): void
    {
        $headers = ['Col1', 'Col2', 'Col3'];
        $rows = [['a', 'b', 'c']];
        $result = $this->service->exportCsv($headers, $rows, 'test');
        $decoded = base64_decode($result['csv']);

        // Remove BOM
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $content = substr($decoded, strlen($bom));

        $lines = explode("\r\n", $content);
        // Header line
        $this->assertSame('Col1;Col2;Col3', $lines[0]);
        // Data line with quoted values
        $this->assertSame('"a";"b";"c"', $lines[1]);
    }

    public function testExportCsvEscapesDoubleQuotesInCells(): void
    {
        $headers = ['Text'];
        $rows = [['He said "hello"']];
        $result = $this->service->exportCsv($headers, $rows, 'test');
        $decoded = base64_decode($result['csv']);

        $this->assertStringContainsString('"He said ""hello"""', $decoded);
    }

    public function testExportCsvHandlesEmptyRows(): void
    {
        $result = $this->service->exportCsv(['H1', 'H2'], [], 'empty');

        $this->assertSame(0, $result['row_count']);
        $decoded = base64_decode($result['csv']);
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $content = substr($decoded, strlen($bom));
        // Only header line + trailing CRLF
        $this->assertSame("H1;H2\r\n", $content);
    }

    public function testExportCsvUsesWindowsLineEndings(): void
    {
        $result = $this->service->exportCsv(['H'], [['V1'], ['V2']], 'test');
        $decoded = base64_decode($result['csv']);

        // Should contain \r\n, not bare \n
        $this->assertStringContainsString("\r\n", $decoded);
        // Remove all \r\n and check there are no stray \n
        $cleaned = str_replace("\r\n", '', $decoded);
        $this->assertStringNotContainsString("\n", $cleaned);
    }

    public function testExportReportReturnsErrorForUnknownType(): void
    {
        $result = $this->service->exportReport('nonexistent_type', []);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Unbekannter Berichtstyp', $result['error']);
    }

    public function testExportReportUtilizationGeneratesCsv(): void
    {
        $data = [
            [
                'assetTypes_name' => 'Camera',
                'assetCategories_name' => 'Video',
                'days_rented' => 20,
                'days_available' => 30,
                'utilization_pct' => 66.67,
                'revenue' => 5000.00,
            ],
        ];

        $result = $this->service->exportReport('utilization', $data);

        $this->assertArrayHasKey('csv', $result);
        $this->assertSame(1, $result['row_count']);
        $this->assertStringContainsString('auslastungsbericht_', $result['filename']);
    }

    public function testExportReportTopClientsIncludesRanking(): void
    {
        $data = [
            ['clients_name' => 'Client A', 'project_count' => 5, 'total_revenue' => 10000, 'paid_revenue' => 8000],
            ['clients_name' => 'Client B', 'project_count' => 3, 'total_revenue' => 5000, 'paid_revenue' => 5000],
        ];

        $result = $this->service->exportReport('top_clients', $data);

        $this->assertSame(2, $result['row_count']);
        $decoded = base64_decode($result['csv']);
        // First data row should have rank 1
        $this->assertStringContainsString('"1"', $decoded);
        // Second data row should have rank 2
        $this->assertStringContainsString('"2"', $decoded);
    }

    public function testExportReportRoiGeneratesCorrectColumns(): void
    {
        $data = [
            [
                'assetTypes_name' => 'Lens',
                'assetCategories_name' => 'Optics',
                'asset_count' => 3,
                'purchase_price' => 9000,
                'day_rate' => 150,
                'total_revenue' => 12000,
                'roi_pct' => 33.33,
                'payback_months' => 8.5,
                'is_profitable' => true,
            ],
        ];

        $result = $this->service->exportReport('roi', $data);

        $this->assertSame(1, $result['row_count']);
        $decoded = base64_decode($result['csv']);
        $this->assertStringContainsString('Ja', $decoded);
        $this->assertStringContainsString('Equipment', $decoded);
    }

    public function testExportReportRoiShowsNeinForUnprofitable(): void
    {
        $data = [
            [
                'assetTypes_name' => 'Tripod',
                'assetCategories_name' => 'Support',
                'asset_count' => 1,
                'purchase_price' => 500,
                'day_rate' => 10,
                'total_revenue' => 200,
                'roi_pct' => -60,
                'payback_months' => null,
                'is_profitable' => false,
            ],
        ];

        $result = $this->service->exportReport('roi', $data);

        $decoded = base64_decode($result['csv']);
        $this->assertStringContainsString('Nein', $decoded);
        // null payback should show '-'
        $this->assertStringContainsString('"-"', $decoded);
    }
}
