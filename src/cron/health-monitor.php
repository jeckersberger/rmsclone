<?php
/**
 * Cron-Job: System Health Monitor
 *
 * Checks overall system health and sends alert emails when issues are detected.
 *
 * Checks performed:
 *   1. Disk space usage (warn >= 80%, critical >= 90%)
 *   2. Database connectivity
 *   3. Stale cron jobs (last run > 24 hours ago)
 *
 * Usage:
 *   php src/cron/health-monitor.php
 *
 * Recommended crontab entry (every 15 minutes):
 *   every 15 min - php /path/to/src/cron/health-monitor.php >> /var/log/adamrms-health.log 2>&1
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../api/notifications/email/email.php';

echo "[" . date('Y-m-d H:i:s') . "] Health monitor started\n";

$alerts = [];

// ---------------------------------------------------------------------------
// 1. Disk space check
// ---------------------------------------------------------------------------
$diskFree  = @disk_free_space('/');
$diskTotal = @disk_total_space('/');

if ($diskFree === false || $diskTotal === false || $diskTotal == 0) {
    $alerts[] = [
        'level'   => 'CRITICAL',
        'check'   => 'Disk Space',
        'message' => 'Unable to determine disk space.',
    ];
} else {
    $usedPercent = round((1 - $diskFree / $diskTotal) * 100, 1);
    $freeHuman   = round($diskFree / 1073741824, 1); // GB

    if ($usedPercent >= 90) {
        $alerts[] = [
            'level'   => 'CRITICAL',
            'check'   => 'Disk Space',
            'message' => "Disk usage at {$usedPercent}% ({$freeHuman} GB free). Immediate action required.",
        ];
    } elseif ($usedPercent >= 80) {
        $alerts[] = [
            'level'   => 'WARNING',
            'check'   => 'Disk Space',
            'message' => "Disk usage at {$usedPercent}% ({$freeHuman} GB free). Consider cleanup.",
        ];
    } else {
        echo "  [OK] Disk: {$usedPercent}% used ({$freeHuman} GB free)\n";
    }
}

// ---------------------------------------------------------------------------
// 2. Database connection check
// ---------------------------------------------------------------------------
try {
    $result = $DBLIB->rawQueryOne('SELECT 1 AS ping');
    if ($result && isset($result['ping'])) {
        echo "  [OK] Database: connected\n";
    } else {
        throw new Exception('Query returned no result');
    }
} catch (Exception $e) {
    $alerts[] = [
        'level'   => 'CRITICAL',
        'check'   => 'Database',
        'message' => 'Database connection failed: ' . $e->getMessage(),
    ];
}

// ---------------------------------------------------------------------------
// 3. Stale cron jobs check (last run > 24 hours ago)
// ---------------------------------------------------------------------------
try {
    $DBLIB->where('completed_at', date('Y-m-d H:i:s', strtotime('-24 hours')), '<');
    $DBLIB->where('status', 'success');
    $DBLIB->orderBy('completed_at', 'DESC');
    $DBLIB->groupBy('job_type');
    $staleJobs = $DBLIB->get('cron_runs', null, ['job_type', 'MAX(completed_at) AS last_run']);

    if ($staleJobs) {
        // Check if there are job types that have not run in the last 24 hours
        // by looking for types with no recent successful run
        $DBLIB->where('completed_at', date('Y-m-d H:i:s', strtotime('-24 hours')), '>=');
        $DBLIB->where('status', 'success');
        $recentTypes = $DBLIB->get('cron_runs', null, ['DISTINCT job_type AS job_type']);
        $recentTypeNames = $recentTypes ? array_column($recentTypes, 'job_type') : [];

        foreach ($staleJobs as $job) {
            if (!in_array($job['job_type'], $recentTypeNames, true)) {
                $alerts[] = [
                    'level'   => 'WARNING',
                    'check'   => 'Cron Jobs',
                    'message' => "Cron job '{$job['job_type']}' has not run successfully since {$job['last_run']} (> 24h).",
                ];
            }
        }
    }
    echo "  [OK] Cron jobs: checked\n";
} catch (Exception $e) {
    // cron_runs table may not exist yet -- non-critical
    echo "  [SKIP] Cron jobs: could not query cron_runs table ({$e->getMessage()})\n";
}

// ---------------------------------------------------------------------------
// 4. Send alert email if any checks failed
// ---------------------------------------------------------------------------
if (!empty($alerts)) {
    echo "\n  [ALERT] " . count($alerts) . " issue(s) detected:\n";
    foreach ($alerts as $a) {
        echo "    [{$a['level']}] {$a['check']}: {$a['message']}\n";
    }

    // Build HTML alert email
    $html  = "<h2>MyRMS Health Alert - " . date('Y-m-d H:i:s') . "</h2>";
    $html .= "<p>The following issue(s) were detected on <strong>" . gethostname() . "</strong>:</p>";
    $html .= "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%;'>";
    $html .= "<tr style='background:#343a40;color:#fff;'><th>Level</th><th>Check</th><th>Details</th></tr>";

    foreach ($alerts as $a) {
        $color = $a['level'] === 'CRITICAL' ? '#dc3545' : '#ffc107';
        $html .= "<tr>";
        $html .= "<td style='background:{$color};color:#fff;font-weight:bold;'>{$a['level']}</td>";
        $html .= "<td>{$a['check']}</td>";
        $html .= "<td>{$a['message']}</td>";
        $html .= "</tr>";
    }
    $html .= "</table>";
    $html .= "<p style='margin-top:16px;color:#6c757d;font-size:0.9em;'>This alert was generated by the MyRMS health monitor cron job.</p>";

    // Determine alert recipient
    $alertEmail = getenv('ALERT_EMAIL');
    if ($alertEmail) {
        // Build a minimal user structure compatible with the sendEmail function
        $alertUser = [
            'userData' => [
                'users_email'  => $alertEmail,
                'users_name1'  => 'System',
                'users_name2'  => 'Admin',
                'users_userid' => 0,
            ],
        ];

        $highestLevel = 'WARNING';
        foreach ($alerts as $a) {
            if ($a['level'] === 'CRITICAL') {
                $highestLevel = 'CRITICAL';
                break;
            }
        }

        $subject = "[{$highestLevel}] MyRMS Health Alert: " . count($alerts) . " issue(s)";
        @sendEmail($alertUser, null, $subject, $html);
        echo "  [>] Alert email sent to: $alertEmail\n";
    } else {
        echo "  [WARN] ALERT_EMAIL not set -- alert email not sent\n";
    }
} else {
    echo "\n  All checks passed.\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Health monitor finished\n";
