<?php
/**
 * Einnahmenueberschussrechnung (EUeR) Service
 *
 * Erstellt eine einfache EUeR-Auswertung fuer Kleinunternehmer
 * nach Anlage EUeR des Einkommensteuergesetzes.
 *
 * Zufluss-/Abfluss-Prinzip: Einnahmen und Ausgaben werden im Jahr
 * des tatsaechlichen Geldflusses erfasst (nicht der Rechnungsstellung).
 */
class EuerService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get EUeR report for a fiscal year
     */
    public function getReport(int $instanceId, int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        // Get categories
        $categories = $this->getCategories($instanceId);

        // Get manual bookings
        $this->db->where('eb.instances_id', $instanceId);
        $this->db->where('eb.booking_date', $startDate, '>=');
        $this->db->where('eb.booking_date', $endDate, '<=');
        $this->db->join('euer_categories ec', 'eb.euer_categories_id=ec.id', 'LEFT');
        $this->db->orderBy('eb.booking_date', 'ASC');
        $bookings = $this->db->get('euer_bookings eb', null, [
            'eb.*', 'ec.category_type', 'ec.euer_line', 'ec.name as category_name'
        ]) ?: [];

        // Get income from paid invoices (Zufluss-Prinzip: paid_date, not invoice date)
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', 'invoice');
        $this->db->where('dl.status', 'paid');
        $this->db->where('dl.paid_date', $startDate, '>=');
        $this->db->where('dl.paid_date', $endDate, '<=');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('dl.paid_date', 'ASC');
        $paidInvoices = $this->db->get('document_lifecycle dl', null, [
            'dl.*', 'p.projects_name', 'c.clients_name'
        ]) ?: [];

        // Compile income
        $totalIncome = 0;
        $incomeItems = [];
        foreach ($paidInvoices as $inv) {
            $amount = (float)($inv['paid_amount'] ?: $inv['gross_amount']);
            $totalIncome += $amount;
            $incomeItems[] = [
                'date' => $inv['paid_date'],
                'description' => "Rechnung {$inv['doc_number']} - {$inv['clients_name']} ({$inv['projects_name']})",
                'amount' => $amount,
                'source' => 'auto',
                'doc_lifecycle_id' => $inv['id'],
            ];
        }

        // Add manual income bookings
        foreach ($bookings as $b) {
            if ($b['category_type'] === 'income') {
                $totalIncome += (float)$b['amount'];
                $incomeItems[] = [
                    'date' => $b['booking_date'],
                    'description' => $b['description'],
                    'amount' => (float)$b['amount'],
                    'source' => 'manual',
                    'booking_id' => $b['id'],
                    'category' => $b['category_name'],
                    'euer_line' => $b['euer_line'],
                ];
            }
        }

        // Compile expenses by category
        $totalExpenses = 0;
        $expensesByCategory = [];
        foreach ($bookings as $b) {
            if ($b['category_type'] === 'expense') {
                $catKey = $b['euer_categories_id'];
                if (!isset($expensesByCategory[$catKey])) {
                    $expensesByCategory[$catKey] = [
                        'category_name' => $b['category_name'],
                        'euer_line' => $b['euer_line'],
                        'total' => 0,
                        'items' => [],
                    ];
                }
                $expensesByCategory[$catKey]['total'] += (float)$b['amount'];
                $expensesByCategory[$catKey]['items'][] = [
                    'date' => $b['booking_date'],
                    'description' => $b['description'],
                    'amount' => (float)$b['amount'],
                    'booking_id' => $b['id'],
                ];
                $totalExpenses += (float)$b['amount'];
            }
        }

        $profit = $totalIncome - $totalExpenses;

        return [
            'year'               => $year,
            'total_income'       => round($totalIncome, 2),
            'total_expenses'     => round($totalExpenses, 2),
            'profit'             => round($profit, 2),
            'income_items'       => $incomeItems,
            'expenses_by_category' => array_values($expensesByCategory),
            'categories'         => $categories,
            'kur_limit'          => 25000, // Ab 2025: 25.000 EUR
            'kur_warning'        => $totalIncome > 22000,
            'kur_exceeded'       => $totalIncome > 25000,
        ];
    }

    /**
     * Get all categories for an instance
     */
    public function getCategories(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('category_type', 'ASC');
        $this->db->orderBy('sort_order', 'ASC');
        return $this->db->get('euer_categories') ?: [];
    }

    /**
     * Add a manual booking
     */
    public function addBooking(int $instanceId, array $data, int $userId): int
    {
        $this->db->insert('euer_bookings', [
            'instances_id'          => $instanceId,
            'euer_categories_id'    => (int)$data['category_id'],
            'booking_date'          => $data['date'],
            'description'           => $data['description'],
            'amount'                => (float)$data['amount'],
            'vat_amount'            => (float)($data['vat_amount'] ?? 0),
            'document_lifecycle_id' => $data['document_lifecycle_id'] ?? null,
            'projects_id'           => $data['projects_id'] ?? null,
            'receipt_s3files_id'    => $data['receipt_s3files_id'] ?? null,
            'created_by'            => $userId,
        ]);
        return $this->db->getInsertId();
    }

    /**
     * Delete a booking
     */
    public function deleteBooking(int $bookingId, int $instanceId): bool
    {
        $this->db->where('id', $bookingId);
        $this->db->where('instances_id', $instanceId);
        return $this->db->delete('euer_bookings');
    }

    /**
     * Get monthly summary for chart
     */
    public function getMonthlySummary(int $instanceId, int $year): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = ['month' => $m, 'income' => 0, 'expenses' => 0];
        }

        // Income from paid invoices
        $sql = "SELECT MONTH(paid_date) as m, SUM(COALESCE(paid_amount, gross_amount)) as total
                FROM document_lifecycle
                WHERE instances_id = ? AND doc_type = 'invoice' AND status = 'paid'
                AND YEAR(paid_date) = ?
                GROUP BY MONTH(paid_date)";
        $incomeRows = $this->db->rawQuery($sql, [$instanceId, $year]) ?: [];
        foreach ($incomeRows as $r) {
            $months[(int)$r['m']]['income'] += (float)$r['total'];
        }

        // Manual income bookings
        $sql = "SELECT MONTH(eb.booking_date) as m, SUM(eb.amount) as total
                FROM euer_bookings eb
                JOIN euer_categories ec ON eb.euer_categories_id = ec.id
                WHERE eb.instances_id = ? AND ec.category_type = 'income'
                AND YEAR(eb.booking_date) = ?
                GROUP BY MONTH(eb.booking_date)";
        $manualIncome = $this->db->rawQuery($sql, [$instanceId, $year]) ?: [];
        foreach ($manualIncome as $r) {
            $months[(int)$r['m']]['income'] += (float)$r['total'];
        }

        // Expense bookings
        $sql = "SELECT MONTH(eb.booking_date) as m, SUM(eb.amount) as total
                FROM euer_bookings eb
                JOIN euer_categories ec ON eb.euer_categories_id = ec.id
                WHERE eb.instances_id = ? AND ec.category_type = 'expense'
                AND YEAR(eb.booking_date) = ?
                GROUP BY MONTH(eb.booking_date)";
        $expenseRows = $this->db->rawQuery($sql, [$instanceId, $year]) ?: [];
        foreach ($expenseRows as $r) {
            $months[(int)$r['m']]['expenses'] += (float)$r['total'];
        }

        return array_values($months);
    }
}
