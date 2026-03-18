<?php

namespace Rms\Services;

use MeekroDB;
use DateTime;
use Throwable;

class ErrorTerminalService
{
    private $db;

    public function __construct(MeekroDB $db)
    {
        $this->db = $db;
    }

    /**
     * Log an error to the system error log
     *
     * @param string $level debug|info|warning|error|critical
     * @param string $source Component or module where error occurred
     * @param string $message Error message
     * @param string|null $stackTrace Stack trace (optional)
     * @param array|null $context Additional context as array (optional)
     * @param int|null $userId User ID if applicable (optional)
     * @param int|null $instanceId Instance ID (required for filtering)
     * @return int The error log ID
     */
    public function log(
        string $level,
        string $source,
        string $message,
        ?string $stackTrace = null,
        ?array $context = null,
        ?int $userId = null,
        ?int $instanceId = null
    ): int {
        $data = [
            'level' => $level,
            'source' => substr($source, 0, 100),
            'message' => $message,
            'stack_trace' => $stackTrace,
            'context' => $context ? json_encode($context) : null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'instances_id' => $instanceId ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('system_error_log', $data);
    }

    /**
     * Log an exception
     *
     * @param Throwable $e The exception
     * @param string|null $source Component name (optional)
     * @param array|null $context Additional context (optional)
     * @param int|null $userId User ID (optional)
     * @param int|null $instanceId Instance ID (optional)
     * @return int The error log ID
     */
    public function logException(
        Throwable $e,
        ?string $source = null,
        ?array $context = null,
        ?int $userId = null,
        ?int $instanceId = null
    ): int {
        $source = $source ?? 'exception';
        $stackTrace = $e->getTraceAsString();
        $message = $e->getMessage();
        $level = 'error';

        // Escalate critical exceptions to critical level
        if (stripos($message, 'critical') !== false || stripos($message, 'fatal') !== false) {
            $level = 'critical';
        } elseif (stripos($message, 'warning') !== false) {
            $level = 'warning';
        }

        if (!$context) {
            $context = [];
        }
        $context['exception_class'] = get_class($e);
        $context['exception_code'] = $e->getCode();

        return $this->log($level, $source, $message, $stackTrace, $context, $userId, $instanceId);
    }

    /**
     * Get errors with filters
     *
     * @param int $instanceId Instance to filter by
     * @param string|null $level Filter by level (optional)
     * @param bool|null $unresolvedOnly Return only unresolved errors (optional)
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array Array of error records
     */
    public function getErrors(
        int $instanceId,
        ?string $level = null,
        ?bool $unresolvedOnly = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $this->db->where('instances_id', $instanceId);

        if ($level) {
            $this->db->where('level', $level);
        }

        if ($unresolvedOnly === true) {
            $this->db->where('is_resolved', false);
        }

        $this->db->orderBy('created_at', 'DESC');

        return $this->db->get('system_error_log', $limit, $offset) ?: [];
    }

    /**
     * Get a single error with all details
     *
     * @param int $id Error log ID
     * @return array|null Error record or null if not found
     */
    public function getError(int $id): ?array
    {
        $this->db->where('id', $id);
        $error = $this->db->getOne('system_error_log');

        if ($error && $error['context']) {
            $error['context'] = json_decode($error['context'], true);
        }

        return $error;
    }

    /**
     * Mark an error as resolved
     *
     * @param int $id Error log ID
     * @param int $userId User ID who resolved it
     * @param string|null $note Optional resolution note
     * @return bool Success
     */
    public function resolveError(int $id, int $userId, ?string $note = null): bool
    {
        $this->db->where('id', $id);
        $this->db->update('system_error_log', [
            'is_resolved' => true,
            'resolved_by' => $userId,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_note' => $note,
        ]);

        return true;
    }

    /**
     * Resolve multiple errors at once
     *
     * @param array $ids Array of error log IDs
     * @param int $userId User ID who resolved them
     * @param string|null $note Optional resolution note
     * @return int Number of errors resolved
     */
    public function resolveMultiple(array $ids, int $userId, ?string $note = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "
            UPDATE system_error_log
            SET is_resolved = true, resolved_by = ?, resolved_at = ?, resolved_note = ?
            WHERE id IN ($placeholders)
        ";

        $params = [$userId, date('Y-m-d H:i:s'), $note];
        array_splice($params, 1, 0, $ids);

        $this->db->rawQuery($query, $params);

        return count($ids);
    }

    /**
     * Get dashboard statistics
     *
     * @param int $instanceId Instance to get stats for
     * @return array Statistics including counts by level and time periods
     */
    public function getStats(int $instanceId): array
    {
        $now = new DateTime();

        // Count by level
        $levels = ['debug', 'info', 'warning', 'error', 'critical'];
        $countByLevel = [];

        foreach ($levels as $level) {
            $this->db->where('instances_id', $instanceId);
            $this->db->where('level', $level);
            $result = $this->db->rawQuery(
                "SELECT COUNT(*) as cnt FROM system_error_log WHERE instances_id = ? AND level = ? AND is_resolved = false",
                [$instanceId, $level]
            );
            $countByLevel[$level] = (int)($result[0]['cnt'] ?? 0);
        }

        // Resolved errors total
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_resolved', true);
        $resolvedResult = $this->db->rawQuery(
            "SELECT COUNT(*) as cnt FROM system_error_log WHERE instances_id = ? AND is_resolved = true",
            [$instanceId]
        );
        $countResolved = (int)($resolvedResult[0]['cnt'] ?? 0);

        // Unresolved errors total
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_resolved', false);
        $unresolvedResult = $this->db->rawQuery(
            "SELECT COUNT(*) as cnt FROM system_error_log WHERE instances_id = ? AND is_resolved = false",
            [$instanceId]
        );
        $countUnresolved = (int)($unresolvedResult[0]['cnt'] ?? 0);

        // Last 24 hours
        $last24h = (clone $now)->modify('-24 hours')->format('Y-m-d H:i:s');
        $result24h = $this->db->rawQuery(
            "SELECT COUNT(*) as cnt FROM system_error_log WHERE instances_id = ? AND created_at >= ? AND is_resolved = false",
            [$instanceId, $last24h]
        );
        $count24h = (int)($result24h[0]['cnt'] ?? 0);

        // Last 7 days
        $last7d = (clone $now)->modify('-7 days')->format('Y-m-d H:i:s');
        $result7d = $this->db->rawQuery(
            "SELECT COUNT(*) as cnt FROM system_error_log WHERE instances_id = ? AND created_at >= ? AND is_resolved = false",
            [$instanceId, $last7d]
        );
        $count7d = (int)($result7d[0]['cnt'] ?? 0);

        // Last 30 days
        $last30d = (clone $now)->modify('-30 days')->format('Y-m-d H:i:s');
        $result30d = $this->db->rawQuery(
            "SELECT COUNT(*) as cnt FROM system_error_log WHERE instances_id = ? AND created_at >= ? AND is_resolved = false",
            [$instanceId, $last30d]
        );
        $count30d = (int)($result30d[0]['cnt'] ?? 0);

        return [
            'by_level' => $countByLevel,
            'total_unresolved' => $countUnresolved,
            'total_resolved' => $countResolved,
            'last_24h' => $count24h,
            'last_7d' => $count7d,
            'last_30d' => $count30d,
        ];
    }

    /**
     * Clear old resolved errors
     *
     * @param int $daysToKeep Number of days to keep errors for
     * @return int Number of errors deleted
     */
    public function clearOld(int $daysToKeep = 90): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$daysToKeep days"));

        $result = $this->db->rawQuery(
            "DELETE FROM system_error_log WHERE is_resolved = true AND resolved_at < ?",
            [$cutoffDate]
        );

        return $this->db->count();
    }

    /**
     * Get error data formatted for export
     *
     * @param int $instanceId Instance to export for
     * @param string|null $level Filter by level (optional)
     * @param string|null $dateFrom Filter from date YYYY-MM-DD (optional)
     * @param string|null $dateTo Filter to date YYYY-MM-DD (optional)
     * @return array Array of error data
     */
    public function getExportData(
        int $instanceId,
        ?string $level = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $this->db->where('instances_id', $instanceId);

        if ($level) {
            $this->db->where('level', $level);
        }

        if ($dateFrom) {
            $this->db->where('created_at', $dateFrom . ' 00:00:00', '>=');
        }

        if ($dateTo) {
            $this->db->where('created_at', $dateTo . ' 23:59:59', '<=');
        }

        $this->db->orderBy('created_at', 'DESC');

        $errors = $this->db->get('system_error_log', 10000) ?: [];

        foreach ($errors as &$error) {
            if ($error['context']) {
                $error['context'] = json_decode($error['context'], true);
            }
        }

        return $errors;
    }

    /**
     * Format an error for developer copy/paste
     *
     * @param int $id Error log ID
     * @return string Formatted error text
     */
    public function formatForDeveloper(int $id): string
    {
        $error = $this->getError($id);

        if (!$error) {
            return "Error #$id not found";
        }

        $output = "=== MyRMS Fehlerprotokoll ===\n";
        $output .= "Datum: " . $error['created_at'] . "\n";
        $output .= "Level: " . strtoupper($error['level']) . "\n";
        $output .= "Quelle: " . $error['source'] . "\n";
        $output .= "Nachricht: " . $error['message'] . "\n";

        if ($error['url']) {
            $output .= "URL: " . $error['url'] . "\n";
        }

        if ($error['user_id']) {
            $output .= "Benutzer: ID " . $error['user_id'] . "\n";
        }

        $output .= "\n";

        if ($error['stack_trace']) {
            $output .= "Stack Trace:\n";
            $output .= $error['stack_trace'] . "\n";
            $output .= "\n";
        }

        if ($error['context']) {
            $output .= "Kontext:\n";
            $output .= json_encode($error['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            $output .= "\n";
        }

        $phpVersion = phpversion();
        $mysqlVersion = $this->getMysqlVersion();
        $rmsVersion = '1.1'; // TODO: Get from config

        $output .= "System: PHP " . $phpVersion . " | MySQL " . $mysqlVersion . " | MyRMS v" . $rmsVersion . "\n";
        $output .= "===========================\n";

        return $output;
    }

    /**
     * Get MySQL version
     *
     * @return string MySQL version
     */
    private function getMysqlVersion(): string
    {
        try {
            $result = $this->db->rawQuery("SELECT VERSION() as version");
            return $result[0]['version'] ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}

/**
 * Static helper class for error capture
 * Call this from catch blocks throughout the application
 */
class ErrorLogger
{
    /**
     * Capture an exception and log it
     *
     * @param Throwable $e Exception to log
     * @param string|null $source Component name (optional)
     * @param array|null $context Additional context (optional)
     * @return void
     */
    public static function capture(
        Throwable $e,
        ?string $source = null,
        ?array $context = null
    ): void {
        global $DBLIB, $AUTH;

        try {
            if (!$DBLIB) {
                return; // Cannot log without database
            }

            $service = new self::class;
            $service = new ErrorTerminalService($DBLIB);

            $userId = null;
            $instanceId = 1;

            if ($AUTH) {
                $userId = $AUTH->data['users_userid'] ?? null;
                $instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 1);
            }

            $service->logException($e, $source, $context, $userId, $instanceId);
        } catch (\Exception $logError) {
            // Silently fail - don't let logging errors break the application
            error_log("ErrorLogger failed: " . $logError->getMessage());
        }
    }
}
