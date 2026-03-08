<?php
/**
 * Health-Check Endpoint
 *
 * Returns the operational status of the application and its dependencies.
 * No authentication required -- intended for load balancers, uptime monitors,
 * and container orchestrators (Docker HEALTHCHECK, Kubernetes liveness probes).
 *
 * HTTP 200  - All checks pass
 * HTTP 503  - One or more checks failed
 *
 * Response format:
 *   {
 *     "status": "ok" | "error",
 *     "checks": {
 *       "database": "ok" | "error: ...",
 *       "disk":     "ok" | "warning: ..." | "error: ...",
 *       "php":      "8.x.x"
 *     },
 *     "timestamp": "2024-01-01T00:00:00+00:00"
 *   }
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$checks = [];
$allOk  = true;

// ---------------------------------------------------------------------------
// 1. Database connection
// ---------------------------------------------------------------------------
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8',
        getenv('DB_HOSTNAME') ?: 'localhost',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_DATABASE') ?: 'adamrms'
    );
    $pdo = new PDO(
        $dsn,
        getenv('DB_USERNAME') ?: 'root',
        getenv('DB_PASSWORD') ?: '',
        [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    // Quick query to confirm the connection is truly alive
    $pdo->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (Exception $e) {
    $checks['database'] = 'error: ' . $e->getMessage();
    $allOk = false;
}

// ---------------------------------------------------------------------------
// 2. Disk space
// ---------------------------------------------------------------------------
$diskFree  = @disk_free_space('/');
$diskTotal = @disk_total_space('/');

if ($diskFree === false || $diskTotal === false || $diskTotal == 0) {
    $checks['disk'] = 'error: unable to determine disk space';
    $allOk = false;
} else {
    $usedPercent = round((1 - $diskFree / $diskTotal) * 100, 1);

    if ($usedPercent >= 95) {
        $checks['disk'] = "error: {$usedPercent}% used";
        $allOk = false;
    } elseif ($usedPercent >= 90) {
        $checks['disk'] = "warning: {$usedPercent}% used";
        // Warning does not cause overall failure, but it is visible
    } else {
        $checks['disk'] = 'ok';
    }
}

// ---------------------------------------------------------------------------
// 3. PHP version
// ---------------------------------------------------------------------------
$checks['php'] = PHP_VERSION;

// Require PHP 8.x as a minimum
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    $allOk = false;
}

// ---------------------------------------------------------------------------
// Response
// ---------------------------------------------------------------------------
$status = $allOk ? 'ok' : 'error';
http_response_code($allOk ? 200 : 503);

echo json_encode([
    'status'    => $status,
    'checks'    => $checks,
    'timestamp' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
