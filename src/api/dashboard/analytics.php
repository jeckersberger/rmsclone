<?php
/**
 * Dashboard Analytics API
 *
 * Liefert erweiterte KPIs und Chart-Daten fuer das Executive Dashboard:
 * - Revenue MTD/YTD mit Vergleich zum Vormonat/Vorjahr
 * - 30-Tage Revenue Trend (Tagesaufloesung)
 * - Cash Flow Forecast (30/60/90 Tage)
 * - Asset Utilization (Top 10)
 * - A/R Aging (0-30, 30-60, 60-90, 90+ Tage)
 * - Top 5 Kunden nach Umsatz
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_STATS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$today = date('Y-m-d');
$response = [];

// ═══════════════════════════════════════════════
//  1. REVENUE KPIs
// ═══════════════════════════════════════════════

$monthStart = date('Y-m-01');
$yearStart = date('Y-01-01');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
$lastYearStart = date('Y-01-01', strtotime('-1 year'));
$lastYearEnd = date('Y-12-31', strtotime('-1 year'));

// MTD Revenue (Rechnungen diesen Monat)
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_date', $monthStart, '>=');
$DBLIB->where('de.document_exports_date', $today, '<=');
$DBLIB->where('de.document_exports_type', 'invoice');
$mtdRevenue = (float)$DBLIB->getValue('document_exports de', 'COALESCE(SUM(de.document_exports_gross), 0)');

// Last Month Revenue (Vergleich)
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_date', $lastMonthStart, '>=');
$DBLIB->where('de.document_exports_date', $lastMonthEnd, '<=');
$DBLIB->where('de.document_exports_type', 'invoice');
$lastMonthRevenue = (float)$DBLIB->getValue('document_exports de', 'COALESCE(SUM(de.document_exports_gross), 0)');

// YTD Revenue
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_date', $yearStart, '>=');
$DBLIB->where('de.document_exports_date', $today, '<=');
$DBLIB->where('de.document_exports_type', 'invoice');
$ytdRevenue = (float)$DBLIB->getValue('document_exports de', 'COALESCE(SUM(de.document_exports_gross), 0)');

// Last Year Same Period (fuer YoY Vergleich)
$lastYearSameDay = date('Y-m-d', strtotime('-1 year'));
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_date', $lastYearStart, '>=');
$DBLIB->where('de.document_exports_date', $lastYearSameDay, '<=');
$DBLIB->where('de.document_exports_type', 'invoice');
$lastYearYtdRevenue = (float)$DBLIB->getValue('document_exports de', 'COALESCE(SUM(de.document_exports_gross), 0)');

// Open Invoices (offene Rechnungen)
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_status', ['sent', 'overdue'], 'IN');
$DBLIB->where('de.document_exports_type', 'invoice');
$openInvoicesTotal = (float)$DBLIB->getValue('document_exports de', 'COALESCE(SUM(de.document_exports_gross), 0)');
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_status', ['sent', 'overdue'], 'IN');
$DBLIB->where('de.document_exports_type', 'invoice');
$openInvoicesCount = (int)$DBLIB->getValue('document_exports de', 'COUNT(*)');

// Overdue Invoices
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_status', 'overdue');
$DBLIB->where('de.document_exports_type', 'invoice');
$overdueTotal = (float)$DBLIB->getValue('document_exports de', 'COALESCE(SUM(de.document_exports_gross), 0)');

$response['kpis'] = [
    'revenue_mtd' => round($mtdRevenue, 2),
    'revenue_last_month' => round($lastMonthRevenue, 2),
    'revenue_mtd_change' => $lastMonthRevenue > 0 ? round(($mtdRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100, 1) : 0,
    'revenue_ytd' => round($ytdRevenue, 2),
    'revenue_ytd_change' => $lastYearYtdRevenue > 0 ? round(($ytdRevenue - $lastYearYtdRevenue) / $lastYearYtdRevenue * 100, 1) : 0,
    'open_invoices_total' => round($openInvoicesTotal, 2),
    'open_invoices_count' => $openInvoicesCount,
    'overdue_total' => round($overdueTotal, 2),
];

// ═══════════════════════════════════════════════
//  2. REVENUE TREND (30 Tage, Tagesaufloesung)
// ═══════════════════════════════════════════════

$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_date', $thirtyDaysAgo, '>=');
$DBLIB->where('de.document_exports_type', 'invoice');
$DBLIB->groupBy('day');
$DBLIB->orderBy('day', 'ASC');
$dailyRevenue = $DBLIB->get('document_exports de', null, [
    'DATE(de.document_exports_date) as day',
    'SUM(de.document_exports_gross) as total'
]) ?: [];

// Alle 30 Tage fuellen (auch Tage ohne Umsatz)
$revenueByDay = [];
foreach ($dailyRevenue as $row) {
    $revenueByDay[$row['day']] = round((float)$row['total'], 2);
}

$response['revenue_trend'] = [];
for ($i = 30; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $response['revenue_trend'][] = [
        'date' => $day,
        'label' => date('d.m.', strtotime($day)),
        'revenue' => $revenueByDay[$day] ?? 0,
    ];
}

// ═══════════════════════════════════════════════
//  3. CASH FLOW FORECAST (30/60/90 Tage)
// ═══════════════════════════════════════════════

// Erwartete Einnahmen: offene Rechnungen nach Faelligkeitsdatum
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_status', ['sent', 'overdue'], 'IN');
$DBLIB->where('de.document_exports_type', 'invoice');
$openInvoices = $DBLIB->get('document_exports de', null, [
    'de.document_exports_gross',
    'de.document_exports_date',
    'de.paid_amount'
]) ?: [];

$cashIn30 = 0; $cashIn60 = 0; $cashIn90 = 0;
$day30 = strtotime('+30 days');
$day60 = strtotime('+60 days');
$day90 = strtotime('+90 days');

foreach ($openInvoices as $inv) {
    $remaining = (float)$inv['document_exports_gross'] - (float)($inv['paid_amount'] ?? 0);
    if ($remaining <= 0) continue;

    // Schaetzung: Zahlung kommt ~30 Tage nach Rechnungsdatum
    $expectedPayDate = strtotime('+30 days', strtotime($inv['document_exports_date']));
    if ($expectedPayDate < time()) $expectedPayDate = strtotime('+7 days'); // Ueberfaellig: Zahlung bald erwartet

    if ($expectedPayDate <= $day30) $cashIn30 += $remaining;
    elseif ($expectedPayDate <= $day60) $cashIn60 += $remaining;
    elseif ($expectedPayDate <= $day90) $cashIn90 += $remaining;
}

$response['cashflow_forecast'] = [
    ['period' => '30 Tage', 'expected_in' => round($cashIn30, 2)],
    ['period' => '60 Tage', 'expected_in' => round($cashIn30 + $cashIn60, 2)],
    ['period' => '90 Tage', 'expected_in' => round($cashIn30 + $cashIn60 + $cashIn90, 2)],
];

// ═══════════════════════════════════════════════
//  4. ASSET UTILIZATION (Top 10 meistgenutzte Assets)
// ═══════════════════════════════════════════════

$DBLIB->where('aa.instances_id', $instanceId);
$DBLIB->where('aa.assetsAssignments_deleted', 0);
$DBLIB->join('assets a', 'aa.assets_id=a.assets_id', 'LEFT');
$DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
$DBLIB->where('a.assets_deleted', 0);
$DBLIB->groupBy('at.assetTypes_id');
$DBLIB->orderBy('usage_count', 'DESC');
$assetUtilization = $DBLIB->get('assetsAssignments aa', 10, [
    'at.assetTypes_name',
    'at.assetTypes_id',
    'COUNT(DISTINCT aa.assetsAssignments_id) as usage_count',
    'COUNT(DISTINCT a.assets_id) as asset_count'
]) ?: [];

$response['asset_utilization'] = [];
foreach ($assetUtilization as $row) {
    $response['asset_utilization'][] = [
        'name' => $row['assetTypes_name'],
        'usage_count' => (int)$row['usage_count'],
        'asset_count' => (int)$row['asset_count'],
        'avg_utilization' => $row['asset_count'] > 0
            ? round($row['usage_count'] / $row['asset_count'], 1)
            : 0,
    ];
}

// ═══════════════════════════════════════════════
//  5. A/R AGING (Altersstruktur offene Forderungen)
// ═══════════════════════════════════════════════

$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_status', ['sent', 'overdue'], 'IN');
$DBLIB->where('de.document_exports_type', 'invoice');
$arInvoices = $DBLIB->get('document_exports de', null, [
    'de.document_exports_gross',
    'de.document_exports_date',
    'de.paid_amount'
]) ?: [];

$aging = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
foreach ($arInvoices as $inv) {
    $remaining = (float)$inv['document_exports_gross'] - (float)($inv['paid_amount'] ?? 0);
    if ($remaining <= 0) continue;

    $daysOld = (int)((time() - strtotime($inv['document_exports_date'])) / 86400);
    if ($daysOld <= 30) $aging['0_30'] += $remaining;
    elseif ($daysOld <= 60) $aging['31_60'] += $remaining;
    elseif ($daysOld <= 90) $aging['61_90'] += $remaining;
    else $aging['90_plus'] += $remaining;
}

$response['ar_aging'] = [
    ['bucket' => '0-30 Tage', 'amount' => round($aging['0_30'], 2)],
    ['bucket' => '31-60 Tage', 'amount' => round($aging['31_60'], 2)],
    ['bucket' => '61-90 Tage', 'amount' => round($aging['61_90'], 2)],
    ['bucket' => '90+ Tage', 'amount' => round($aging['90_plus'], 2)],
];

// ═══════════════════════════════════════════════
//  6. TOP 5 KUNDEN NACH UMSATZ (YTD)
// ═══════════════════════════════════════════════

$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_date', $yearStart, '>=');
$DBLIB->where('de.document_exports_type', 'invoice');
$DBLIB->join('projects p', 'de.projects_id=p.projects_id', 'LEFT');
$DBLIB->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
$DBLIB->where('c.clients_id IS NOT NULL');
$DBLIB->groupBy('c.clients_id');
$DBLIB->orderBy('total_revenue', 'DESC');
$topClients = $DBLIB->get('document_exports de', 5, [
    'c.clients_id', 'c.clients_name',
    'SUM(de.document_exports_gross) as total_revenue',
    'COUNT(de.document_exports_id) as invoice_count'
]) ?: [];

$response['top_clients'] = [];
foreach ($topClients as $client) {
    $response['top_clients'][] = [
        'name' => $client['clients_name'],
        'revenue' => round((float)$client['total_revenue'], 2),
        'invoices' => (int)$client['invoice_count'],
    ];
}

// ═══════════════════════════════════════════════
//  7. MONATLICHER REVENUE VERGLEICH (12 Monate)
// ═══════════════════════════════════════════════

$twelveMonthsAgo = date('Y-m-01', strtotime('-11 months'));
$DBLIB->where('de.instances_id', $instanceId);
$DBLIB->where('de.document_exports_date', $twelveMonthsAgo, '>=');
$DBLIB->where('de.document_exports_type', 'invoice');
$DBLIB->groupBy('month');
$DBLIB->orderBy('month', 'ASC');
$monthlyRevenue = $DBLIB->get('document_exports de', null, [
    "DATE_FORMAT(de.document_exports_date, '%Y-%m') as month",
    'SUM(de.document_exports_gross) as total'
]) ?: [];

$revenueByMonth = [];
foreach ($monthlyRevenue as $row) {
    $revenueByMonth[$row['month']] = round((float)$row['total'], 2);
}

$response['monthly_revenue'] = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-{$i} months"));
    $response['monthly_revenue'][] = [
        'month' => $month,
        'label' => date('M Y', strtotime($month . '-01')),
        'revenue' => $revenueByMonth[$month] ?? 0,
    ];
}

finish(true, null, $response);

/** @OA\Get(
 *     path="/dashboard/analytics.php",
 *     summary="Dashboard Analytics",
 *     description="Executive dashboard KPIs, revenue trends, cash flow forecast, asset utilization, and A/R aging",
 *     operationId="dashboardAnalytics",
 *     tags={"dashboard"},
 *     @OA\Response(
 *         response="200",
 *         description="Analytics data"
 *     )
 * )
 */
