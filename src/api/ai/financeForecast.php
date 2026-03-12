<?php
/**
 * Finanz-Prognosen: Analysiert historische Daten und prognostiziert
 * Umsatz, Cashflow und Trends.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('finance_forecast')) {
    finish(false, ["code" => "DISABLED", "message" => "Finanz-Prognosen sind deaktiviert."]);
}

$months = (int)($_POST['months'] ?? 3); // Prognose-Zeitraum
$months = min(max($months, 1), 12);

// Get monthly revenue for last 12 months
$sql = "SELECT DATE_FORMAT(dl.created_at, '%Y-%m') as month,
               SUM(CASE WHEN dl.doc_type = 'invoice' THEN dl.gross_amount ELSE 0 END) as revenue,
               SUM(CASE WHEN dl.doc_type = 'invoice' AND dl.status = 'paid' THEN dl.gross_amount ELSE 0 END) as paid,
               SUM(CASE WHEN dl.doc_type = 'invoice' AND dl.status = 'overdue' THEN dl.gross_amount ELSE 0 END) as overdue,
               COUNT(DISTINCT dl.projects_id) as project_count
        FROM document_lifecycle dl
        WHERE dl.instances_id = ?
        AND dl.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(dl.created_at, '%Y-%m')
        ORDER BY month ASC";
$monthlyRevenue = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];

// Get upcoming projects (future revenue)
$sql = "SELECT p.projects_name, p.projects_dateStart, p.projects_dateEnd, c.clients_name,
               (SELECT SUM(COALESCE(aa.assetsAssignments_customPrice, at.assetTypes_dayRate) *
                   GREATEST(1, DATEDIFF(p.projects_dateEnd, p.projects_dateStart)))
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE aa.projects_id = p.projects_id AND aa.assetsAssignments_deleted = 0
               ) as estimated_value
        FROM projects p
        LEFT JOIN clients c ON p.clients_id = c.clients_id
        WHERE p.instances_id = ? AND p.projects_dateStart >= CURDATE()
        AND p.projects_deleted = 0
        ORDER BY p.projects_dateStart ASC LIMIT 20";
$upcoming = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];

// Get expense data (EUeR)
$sql = "SELECT DATE_FORMAT(eb.booking_date, '%Y-%m') as month,
               SUM(eb.amount) as expenses
        FROM euer_bookings eb
        WHERE eb.instances_id = ? AND eb.type = 'expense'
        AND eb.booking_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(eb.booking_date, '%Y-%m')
        ORDER BY month ASC";
$monthlyExpenses = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];

$context = "Monatliche Umsaetze (letzte 12 Monate):\n";
foreach ($monthlyRevenue as $r) {
    $context .= "- {$r['month']}: Umsatz {$r['revenue']} EUR, davon bezahlt {$r['paid']} EUR, ueberfaellig {$r['overdue']} EUR, Projekte: {$r['project_count']}\n";
}

$context .= "\nMonatliche Ausgaben:\n";
foreach ($monthlyExpenses as $e) {
    $context .= "- {$e['month']}: {$e['expenses']} EUR\n";
}

$context .= "\nKommende Projekte:\n";
foreach ($upcoming as $u) {
    $context .= "- {$u['projects_name']} ({$u['clients_name']}): {$u['projects_dateStart']} bis {$u['projects_dateEnd']}, geschaetzter Wert: " . ($u['estimated_value'] ?: '?') . " EUR\n";
}

$context .= "\nPrognose-Zeitraum: {$months} Monate\n";

$systemPrompt = <<<'PROMPT'
Du bist ein Finanzanalyst fuer ein Equipment-Verleihunternehmen.
Analysiere die historischen Daten und erstelle eine Prognose.

Gib die Antwort als JSON zurueck:
{
  "monthly_forecast": [
    {"month": "2026-04", "revenue_forecast": 0.00, "expense_forecast": 0.00, "profit_forecast": 0.00}
  ],
  "trend": "steigend|stabil|fallend",
  "avg_monthly_revenue": 0.00,
  "avg_monthly_profit": 0.00,
  "cashflow_risk": "niedrig|mittel|hoch",
  "insights": ["Erkenntnis 1", "Erkenntnis 2"],
  "recommendations": ["Empfehlung 1", "Empfehlung 2"],
  "confidence": 0.7
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('finance_forecast', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$forecast = json_decode($text, true);
if (!$forecast && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $forecast = json_decode($m[0], true);
}

finish(true, null, [
    'forecast' => $forecast ?: ['insights' => [$text]],
    'data_months' => count($monthlyRevenue),
    'upcoming_projects' => count($upcoming),
]);
