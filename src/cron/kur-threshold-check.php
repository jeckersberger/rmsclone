<?php
/**
 * Cron-Job: KUR-Umsatzgrenzen pruefen und E-Mail-Warnungen senden
 *
 * Aufruf: php src/cron/kur-threshold-check.php
 * Empfohlener Crontab-Eintrag: 0 8 1 * * php /path/to/src/cron/kur-threshold-check.php >> /var/log/adamrms-cron.log 2>&1
 *
 * Funktionen:
 *   1. Aktuellen Jahresumsatz pro Instance berechnen
 *   2. E-Mail-Warnung bei 80%, 90% und 100% der KUR-Grenze (25.000 EUR ab 2025)
 *   3. Automatischer Wechsel zur Regelbesteuerung bei Ueberschreitung
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';

echo "[" . date('Y-m-d H:i:s') . "] KUR threshold check started\n";

// KUR-Grenze (ab 2025: 25.000 EUR)
$KUR_LIMIT = 25000;
$WARN_THRESHOLDS = [80, 90, 100];

$year = (int)date('Y');

$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name', 'instances_kurEnabled']);

$totalWarnings = 0;
$totalTransitions = 0;

foreach ($instances as $inst) {
    $instanceId = $inst['instances_id'];

    // Nur KUR-Instances pruefen
    if (empty($inst['instances_kurEnabled'])) continue;

    // Jahresumsatz berechnen (bezahlte Rechnungen, Zufluss-Prinzip)
    $sql = "SELECT COALESCE(SUM(COALESCE(paid_amount, gross_amount)), 0) as total_revenue
            FROM document_lifecycle
            WHERE instances_id = ? AND doc_type = 'invoice' AND status = 'paid'
            AND YEAR(paid_date) = ?";
    $result = $DBLIB->rawQuery($sql, [$instanceId, $year]);
    $totalRevenue = (float)($result[0]['total_revenue'] ?? 0);

    // Zusaetzlich manuelle Einnahmen aus EUeR-Buchungen
    $sql = "SELECT COALESCE(SUM(eb.amount), 0) as manual_income
            FROM euer_bookings eb
            JOIN euer_categories ec ON eb.euer_categories_id = ec.id
            WHERE eb.instances_id = ? AND ec.category_type = 'income'
            AND YEAR(eb.booking_date) = ?";
    $manualResult = $DBLIB->rawQuery($sql, [$instanceId, $year]);
    $totalRevenue += (float)($manualResult[0]['manual_income'] ?? 0);

    $percentage = ($KUR_LIMIT > 0) ? round(($totalRevenue / $KUR_LIMIT) * 100, 1) : 0;

    echo "  [{$inst['instances_name']}] Umsatz {$year}: " . number_format($totalRevenue, 2, ',', '.') . " EUR ({$percentage}% von " . number_format($KUR_LIMIT, 0, ',', '.') . " EUR)\n";

    // Schwellenwerte pruefen
    foreach ($WARN_THRESHOLDS as $threshold) {
        if ($percentage >= $threshold) {
            // Pruefen ob Warnung schon gesendet wurde (dieses Jahr, dieser Schwellenwert)
            $DBLIB->where('instances_id', $instanceId);
            $DBLIB->where('job_type', 'kur_threshold_warning');
            $DBLIB->where('YEAR(completed_at)', $year);
            $DBLIB->where("JSON_EXTRACT(details_json, '$.threshold')", $threshold);
            $existing = $DBLIB->getOne('cron_runs');

            if ($existing) continue; // Warnung bereits gesendet

            // Warnung senden an alle Admins der Instance
            $DBLIB->where('usersInstances.instances_id', $instanceId);
            $DBLIB->join('users', 'users.users_userid=usersInstances.users_userid', 'INNER');
            $DBLIB->where('usersInstances.instancesPermissions_useAdmin', 1);
            $admins = $DBLIB->get('usersInstances', null, ['users.users_userid', 'users.users_email', 'users.users_name1', 'users.users_name2']);

            $revenueFormatted = number_format($totalRevenue, 2, ',', '.');
            $limitFormatted = number_format($KUR_LIMIT, 0, ',', '.');

            if ($threshold >= 100) {
                $subject = "ACHTUNG: KUR-Umsatzgrenze ueberschritten!";
                $body = "<h2>Kleinunternehmerregelung - Umsatzgrenze ueberschritten</h2>"
                    . "<p>Ihr Jahresumsatz {$year} betraegt <strong>{$revenueFormatted} EUR</strong> "
                    . "und hat die Grenze von <strong>{$limitFormatted} EUR</strong> ueberschritten.</p>"
                    . "<p><strong>Konsequenz:</strong> Ab dem naechsten Kalenderjahr sind Sie zur Regelbesteuerung verpflichtet. "
                    . "Die Kleinunternehmerregelung wurde automatisch fuer das naechste Jahr deaktiviert.</p>"
                    . "<p>Bitte wenden Sie sich an Ihren Steuerberater fuer weitere Schritte.</p>";

                // Automatischer Uebergang: KUR fuer NAECHSTES Jahr deaktivieren
                // (im aktuellen Jahr bleibt KUR bestehen, Uebergang gilt ab Folgejahr)
                $DBLIB->where('instances_id', $instanceId);
                $DBLIB->update('instances', [
                    'instances_kurTransitionYear' => $year + 1,
                    'instances_kurTransitionReason' => "Umsatzgrenze {$limitFormatted} EUR ueberschritten am " . date('d.m.Y') . " (Umsatz: {$revenueFormatted} EUR)",
                ]);
                $totalTransitions++;
                echo "    [!!] KUR-Uebergang markiert fuer " . ($year + 1) . "\n";
            } else {
                $subject = "KUR-Warnung: {$threshold}% der Umsatzgrenze erreicht";
                $body = "<h2>Kleinunternehmerregelung - Umsatzwarnung</h2>"
                    . "<p>Ihr Jahresumsatz {$year} betraegt <strong>{$revenueFormatted} EUR</strong> "
                    . "und hat <strong>{$percentage}%</strong> der KUR-Grenze von <strong>{$limitFormatted} EUR</strong> erreicht.</p>"
                    . "<p>Bitte beachten Sie: Bei Ueberschreitung der Grenze entfaellt die Kleinunternehmerregelung ab dem Folgejahr.</p>"
                    . "<p>Empfehlung: Besprechen Sie die Situation mit Ihrem Steuerberater.</p>";
            }

            foreach ($admins as $admin) {
                try {
                    $userData = [
                        'userData' => [
                            'users_userid' => $admin['users_userid'],
                            'users_email' => $admin['users_email'],
                            'users_name1' => $admin['users_name1'],
                            'users_name2' => $admin['users_name2'],
                        ]
                    ];

                    $emailBody = "<html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>"
                        . $body
                        . "<hr><p style='color:#999;font-size:12px;'>Diese E-Mail wurde automatisch von {$CONFIG['PROJECT_NAME']} generiert.</p>"
                        . "</body></html>";

                    // Nutze den konfigurierten E-Mail-Provider
                    $providerClass = $CONFIGCLASS->get('EMAILS_PROVIDER') ?? '';
                    if ($providerClass && class_exists($providerClass . 'Handler')) {
                        $handler = $providerClass . 'Handler';
                        $handler::sendEmail($userData, $subject, $emailBody);
                    }
                } catch (\Exception $e) {
                    echo "    [ERR] E-Mail an {$admin['users_email']} fehlgeschlagen: {$e->getMessage()}\n";
                }
            }

            // Warnung protokollieren
            $DBLIB->insert('cron_runs', [
                'instances_id' => $instanceId,
                'job_type' => 'kur_threshold_warning',
                'status' => 'success',
                'items_processed' => count($admins),
                'details_json' => json_encode([
                    'threshold' => $threshold,
                    'percentage' => $percentage,
                    'revenue' => $totalRevenue,
                    'limit' => $KUR_LIMIT,
                    'year' => $year,
                    'admins_notified' => count($admins),
                    'transition' => $threshold >= 100,
                ]),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            $totalWarnings++;
            echo "    [!] {$threshold}%-Warnung gesendet an " . count($admins) . " Admin(s)\n";
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done. {$totalWarnings} warnings sent, {$totalTransitions} transitions marked.\n";
