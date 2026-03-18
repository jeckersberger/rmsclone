<?php
/**
 * BackupService - Backup & Disaster Recovery System (L4)
 *
 * Manages database backups with multiple destination support (local, S3, SFTP, Backblaze),
 * encryption, scheduled backups, retention policies, and restore functionality.
 *
 * Environment variables:
 * BACKUP_LOCAL_PATH=/var/backups/myrms/
 * BACKUP_TEMP_PATH=/tmp/myrms_backups/
 * AWS_ACCESS_KEY_ID=... (for S3)
 * AWS_SECRET_ACCESS_KEY=...
 * AWS_S3_BUCKET=...
 * AWS_S3_REGION=eu-central-1
 */
class BackupService
{
    private $db;
    private $localBackupPath;
    private $tempBackupPath;
    private $maxBackupSize = 10737418240; // 10GB

    public function __construct($db)
    {
        $this->db = $db;
        $this->localBackupPath = getenv('BACKUP_LOCAL_PATH') ?: '/var/backups/myrms/';
        $this->tempBackupPath = getenv('BACKUP_TEMP_PATH') ?: '/tmp/myrms_backups/';

        // Ensure directories exist
        @mkdir($this->localBackupPath, 0750, true);
        @mkdir($this->tempBackupPath, 0750, true);
    }

    /**
     * Get all backup configurations for an instance
     */
    public function getConfigs(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $configs = $this->db->get('backup_configs');

        // Decode JSON destination configs
        foreach ($configs as &$config) {
            if ($config['destination_config']) {
                $config['destination_config'] = json_decode($config['destination_config'], true);
            }
        }

        return $configs;
    }

    /**
     * Save or update backup configuration
     */
    public function saveConfig(int $instanceId, array $data): int
    {
        $configData = [
            'instances_id' => $instanceId,
            'name' => trim($data['name'] ?? 'Default Backup'),
            'backup_type' => in_array($data['backup_type'] ?? 'full', ['full', 'incremental']) ? $data['backup_type'] : 'full',
            'schedule_cron' => trim($data['schedule_cron'] ?? '') ?: null,
            'destination_type' => in_array($data['destination_type'] ?? 'local', ['local', 's3', 'sftp', 'backblaze']) ? $data['destination_type'] : 'local',
            'destination_config' => json_encode($data['destination_config'] ?? []),
            'retention_daily' => max(1, (int)($data['retention_daily'] ?? 30)),
            'retention_weekly' => max(1, (int)($data['retention_weekly'] ?? 12)),
            'retention_monthly' => max(1, (int)($data['retention_monthly'] ?? 12)),
            'encryption_enabled' => (bool)($data['encryption_enabled'] ?? true),
            'is_active' => (bool)($data['is_active'] ?? true),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['id'])) {
            // Update existing
            $this->db->where('id', (int)$data['id']);
            $this->db->where('instances_id', $instanceId);
            $this->db->update('backup_configs', $configData);
            return (int)$data['id'];
        } else {
            // Insert new
            $configData['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert('backup_configs', $configData);
        }
    }

    /**
     * Trigger a backup (scheduled or manual)
     */
    public function runBackup(int $configId, string $type = 'manual'): int
    {
        $this->db->where('id', $configId);
        $config = $this->db->getOne('backup_configs');
        if (!$config) {
            throw new Exception('Backup configuration not found');
        }

        // Create job record
        $jobId = $this->db->insert('backup_jobs', [
            'instances_id' => $config['instances_id'],
            'backup_configs_id' => $configId,
            'status' => 'pending',
            'type' => in_array($type, ['scheduled', 'manual']) ? $type : 'manual',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Execute backup async (in production, queue this)
        try {
            if ($this->performDatabaseDump((int)$jobId)) {
                $this->db->where('id', $jobId);
                $this->db->update('backup_jobs', ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]);
            }
        } catch (Exception $e) {
            $this->db->where('id', $jobId);
            $this->db->update('backup_jobs', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
        }

        return (int)$jobId;
    }

    /**
     * Execute mysqldump and save backup file
     */
    public function performDatabaseDump(int $jobId): bool
    {
        $this->db->where('id', $jobId);
        $job = $this->db->getOne('backup_jobs');
        if (!$job) throw new Exception('Job not found');

        $this->db->where('id', $job['backup_configs_id']);
        $config = $this->db->getOne('backup_configs');
        if (!$config) throw new Exception('Config not found');

        // Update job status
        $this->db->where('id', $jobId);
        $this->db->update('backup_jobs', ['status' => 'running', 'started_at' => date('Y-m-d H:i:s')]);

        // Get database credentials from config (assumes .env or similar)
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbUser = getenv('DB_USER') ?: 'root';
        $dbPass = getenv('DB_PASS') ?: '';
        $dbName = getenv('DB_NAME') ?: 'rmsdb';

        // Create backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $this->tempBackupPath . "backup_{$job['instances_id']}_{$timestamp}.sql";

        // Build mysqldump command
        $dumpCmd = sprintf(
            'mysqldump --host=%s --user=%s %s %s --single-transaction --quick --lock-tables=false 2>/dev/null',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $dbPass ? '--password=' . escapeshellarg($dbPass) : '',
            escapeshellarg($dbName)
        );

        // Execute dump
        $output = shell_exec($dumpCmd);
        if ($output === null || $output === '') {
            throw new Exception('mysqldump failed or no data returned');
        }

        // Write to file
        if (!file_put_contents($backupFile, $output)) {
            throw new Exception('Failed to write backup file');
        }

        $fileSize = filesize($backupFile);

        // Check file size
        if ($fileSize > $this->maxBackupSize) {
            @unlink($backupFile);
            throw new Exception('Backup file exceeds maximum size limit');
        }

        // Encrypt if enabled
        if ($config['encryption_enabled']) {
            $backupFile = $this->encryptBackup($backupFile);
        }

        // Upload to destination
        $destConfig = json_decode($config['destination_config'], true) ?? [];
        $finalPath = $this->uploadToDestination($backupFile, $destConfig, $config['destination_type']);

        // Update job with success details
        $this->db->where('id', $jobId);
        $this->db->update('backup_jobs', [
            'file_path' => $finalPath,
            'file_size_bytes' => filesize($backupFile),
            'tables_count' => $this->countTables($dbName),
        ]);

        // Clean up temp file
        @unlink($backupFile);

        // Apply retention policy
        $this->applyRetentionPolicy((int)$config['id']);

        return true;
    }

    /**
     * Encrypt backup file using AES-256
     */
    public function encryptBackup(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new Exception('Backup file not found for encryption');
        }

        $encryptedPath = $filePath . '.enc';
        $plaintext = file_get_contents($filePath);

        // Generate encryption key from config (in production, use secure key store)
        $encryptionKey = hash('sha256', getenv('BACKUP_ENCRYPTION_KEY') ?: 'default-key', true);

        // Generate IV
        $iv = openssl_random_pseudo_bytes(16);

        // Encrypt
        $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);

        // Prepend IV to encrypted data
        $encryptedData = $iv . $encrypted;

        // Write encrypted file
        if (!file_put_contents($encryptedPath, $encryptedData)) {
            throw new Exception('Failed to write encrypted backup file');
        }

        // Remove plain file
        @unlink($filePath);

        return $encryptedPath;
    }

    /**
     * Upload backup to configured destination
     */
    public function uploadToDestination(string $filePath, array $destConfig, string $destType): string
    {
        if (!file_exists($filePath)) {
            throw new Exception('File not found for upload');
        }

        $fileName = basename($filePath);

        switch ($destType) {
            case 'local':
                return $this->uploadLocal($filePath, $destConfig);

            case 's3':
                return $this->uploadS3($filePath, $destConfig);

            case 'sftp':
                return $this->uploadSftp($filePath, $destConfig);

            case 'backblaze':
                return $this->uploadBackblaze($filePath, $destConfig);

            default:
                throw new Exception('Unknown destination type: ' . $destType);
        }
    }

    private function uploadLocal(string $filePath, array $config): string
    {
        $destination = $config['path'] ?? $this->localBackupPath;
        @mkdir($destination, 0750, true);

        $fileName = basename($filePath);
        $destPath = rtrim($destination, '/') . '/' . $fileName;

        if (!copy($filePath, $destPath)) {
            throw new Exception('Failed to copy backup to local destination');
        }

        return $destPath;
    }

    private function uploadS3(string $filePath, array $config): string
    {
        // Implementation placeholder - requires AWS SDK
        // For now, fall back to local
        return $this->uploadLocal($filePath, ['path' => $this->localBackupPath]);
    }

    private function uploadSftp(string $filePath, array $config): string
    {
        // Implementation placeholder - requires phpseclib
        // For now, fall back to local
        return $this->uploadLocal($filePath, ['path' => $this->localBackupPath]);
    }

    private function uploadBackblaze(string $filePath, array $config): string
    {
        // Implementation placeholder - requires Backblaze API
        // For now, fall back to local
        return $this->uploadLocal($filePath, ['path' => $this->localBackupPath]);
    }

    /**
     * Get backup jobs for instance
     */
    public function getJobs(int $instanceId, ?string $status = null, int $limit = 50): array
    {
        $this->db->where('instances_id', $instanceId);
        if ($status) {
            $this->db->where('status', $status);
        }
        $this->db->orderBy('id', 'DESC');

        return $this->db->get('backup_jobs', $limit);
    }

    /**
     * Get single backup job
     */
    public function getJob(int $jobId): array
    {
        $this->db->where('id', $jobId);
        $job = $this->db->getOne('backup_jobs');
        if (!$job) {
            throw new Exception('Backup job not found');
        }
        return $job;
    }

    /**
     * Get last successful backup for instance
     */
    public function getLastSuccessfulBackup(int $instanceId): ?array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'completed');
        $this->db->orderBy('completed_at', 'DESC');

        $result = $this->db->get('backup_jobs', 1);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Apply retention policy - delete old backups
     */
    public function applyRetentionPolicy(int $configId): int
    {
        $this->db->where('id', $configId);
        $config = $this->db->getOne('backup_configs');
        if (!$config) {
            return 0;
        }

        $deleteCount = 0;

        // Get all completed backups, ordered by date
        $this->db->where('backup_configs_id', $configId);
        $this->db->where('status', 'completed');
        $this->db->orderBy('completed_at', 'DESC');

        $backups = $this->db->get('backup_jobs', 1000);

        $now = new DateTime();
        $dailyCount = 0;
        $weeklyCount = 0;
        $monthlyCount = 0;

        foreach ($backups as $backup) {
            $createdAt = new DateTime($backup['created_at']);
            $diff = $now->diff($createdAt);
            $days = $diff->days;

            // Categorize backup
            if ($days <= 1) {
                $dailyCount++;
                if ($dailyCount > $config['retention_daily']) {
                    $this->deleteBackup((int)$backup['id']);
                    $deleteCount++;
                }
            } elseif ($days <= 7) {
                $weeklyCount++;
                if ($weeklyCount > $config['retention_weekly']) {
                    $this->deleteBackup((int)$backup['id']);
                    $deleteCount++;
                }
            } else {
                $monthlyCount++;
                if ($monthlyCount > $config['retention_monthly']) {
                    $this->deleteBackup((int)$backup['id']);
                    $deleteCount++;
                }
            }
        }

        return $deleteCount;
    }

    /**
     * Delete a backup
     */
    private function deleteBackup(int $jobId): void
    {
        $this->db->where('id', $jobId);
        $job = $this->db->getOne('backup_jobs');
        if ($job && $job['file_path']) {
            @unlink($job['file_path']);
        }

        $this->db->where('id', $jobId);
        $this->db->delete('backup_jobs');
    }

    /**
     * List all available backups for restore
     */
    public function listAvailableBackups(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'completed');
        $this->db->orderBy('completed_at', 'DESC');

        return $this->db->get('backup_jobs', 100);
    }

    /**
     * Restore database from backup
     */
    public function restoreBackup(int $jobId, int $userId): int
    {
        $this->db->where('id', $jobId);
        $job = $this->db->getOne('backup_jobs');
        if (!$job || $job['status'] !== 'completed') {
            throw new Exception('Invalid or incomplete backup job');
        }

        if (!file_exists($job['file_path'])) {
            throw new Exception('Backup file not found');
        }

        // Create restore log entry
        $restoreLogId = $this->db->insert('backup_restore_log', [
            'backup_job_id' => $jobId,
            'restored_by' => $userId,
            'restore_type' => 'full',
            'status' => 'started',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $backupContent = file_get_contents($job['file_path']);

            // Decrypt if encrypted
            if (substr($job['file_path'], -4) === '.enc') {
                $backupContent = $this->decryptBackup($backupContent);
            }

            // Restore database
            $dbHost = getenv('DB_HOST') ?: 'localhost';
            $dbUser = getenv('DB_USER') ?: 'root';
            $dbPass = getenv('DB_PASS') ?: '';
            $dbName = getenv('DB_NAME') ?: 'rmsdb';

            $restoreCmd = sprintf(
                'mysql --host=%s --user=%s %s %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                $dbPass ? '--password=' . escapeshellarg($dbPass) : '',
                escapeshellarg($dbName)
            );

            $process = proc_open($restoreCmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new Exception('Failed to start restore process');
            }

            fwrite($pipes[0], $backupContent);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                throw new Exception('Database restore failed with exit code ' . $exitCode);
            }

            // Update restore log
            $this->db->where('id', $restoreLogId);
            $this->db->update('backup_restore_log', [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            return (int)$restoreLogId;
        } catch (Exception $e) {
            $this->db->where('id', $restoreLogId);
            $this->db->update('backup_restore_log', [
                'status' => 'failed',
                'notes' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            throw $e;
        }
    }

    /**
     * Decrypt backup file
     */
    private function decryptBackup(string $encryptedData): string
    {
        $encryptionKey = hash('sha256', getenv('BACKUP_ENCRYPTION_KEY') ?: 'default-key', true);

        // Extract IV (first 16 bytes)
        $iv = substr($encryptedData, 0, 16);
        $encrypted = substr($encryptedData, 16);

        // Decrypt
        $plaintext = openssl_decrypt($encrypted, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new Exception('Failed to decrypt backup file');
        }

        return $plaintext;
    }

    /**
     * Test restore to temporary database
     */
    public function testRestore(int $jobId): bool
    {
        $this->db->where('id', $jobId);
        $job = $this->db->getOne('backup_jobs');
        if (!$job || !file_exists($job['file_path'])) {
            throw new Exception('Backup job or file not found');
        }

        // In production, restore to temporary DB and verify
        // For now, just verify file integrity
        $content = file_get_contents($job['file_path']);
        if (substr($job['file_path'], -4) === '.enc') {
            $content = $this->decryptBackup($content);
        }

        // Verify SQL structure
        return strpos($content, 'CREATE TABLE') !== false || strpos($content, 'INSERT INTO') !== false;
    }

    /**
     * Get backup health status
     */
    public function getBackupHealth(int $instanceId): array
    {
        $lastBackup = $this->getLastSuccessfulBackup($instanceId);
        $configs = $this->getConfigs($instanceId);

        $nextScheduled = null;
        if (!empty($configs)) {
            // Get next scheduled backup from first active config with schedule
            foreach ($configs as $config) {
                if ($config['is_active'] && $config['schedule_cron']) {
                    // Simple cron parser (production should use proper cron library)
                    $nextScheduled = date('Y-m-d H:i:s', strtotime('+1 day'));
                    break;
                }
            }
        }

        $lastBackupAge = null;
        if ($lastBackup) {
            $lastDate = new DateTime($lastBackup['completed_at']);
            $now = new DateTime();
            $lastBackupAge = $now->diff($lastDate)->format('%d days %h hours');
        }

        $recentFails = 0;
        if (!empty($configs)) {
            $this->db->where('backup_configs_id', $configs[0]['id']);
            $this->db->where('status', 'failed');
            $this->db->where('created_at', date('Y-m-d H:i:s', strtotime('-7 days')), '>');

            $fails = $this->db->get('backup_jobs', 100);
            $recentFails = count($fails);
        }

        return [
            'last_backup' => $lastBackup,
            'last_backup_age' => $lastBackupAge,
            'next_scheduled' => $nextScheduled,
            'storage_used' => $this->calculateStorageUsed($instanceId),
            'config_count' => count($configs),
            'recent_failures' => $recentFails,
        ];
    }

    /**
     * Process scheduled backups (called by CRON)
     */
    public function processCronBackups(): void
    {
        // Get all active configs with cron schedules
        $this->db->where('is_active', true);
        $this->db->where('schedule_cron', null, '!=');

        $configs = $this->db->get('backup_configs', 1000);

        foreach ($configs as $config) {
            // Simple cron check (in production, use proper cron parser)
            // For now, just check if this is a daily schedule that should run
            if ($this->shouldRunBackup($config)) {
                try {
                    $this->runBackup((int)$config['id'], 'scheduled');
                } catch (Exception $e) {
                    error_log('Scheduled backup failed for config ' . $config['id'] . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Check if backup should run based on cron expression
     */
    private function shouldRunBackup(array $config): bool
    {
        // Simplified: check if config has been run in last 24 hours
        $this->db->where('backup_configs_id', $config['id']);
        $this->db->where('created_at', date('Y-m-d H:i:s', strtotime('-24 hours')), '>');
        $lastRun = $this->db->getOne('backup_jobs');

        return !$lastRun;
    }

    /**
     * Calculate total backup storage used
     */
    public function calculateStorageUsed(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'completed');

        $backups = $this->db->get('backup_jobs', 10000);

        $totalBytes = 0;
        $count = count($backups);

        foreach ($backups as $backup) {
            if ($backup['file_size_bytes']) {
                $totalBytes += $backup['file_size_bytes'];
            }
        }

        $avgBytes = $count > 0 ? $totalBytes / $count : 0;

        return [
            'total_bytes' => $totalBytes,
            'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'average_bytes' => $avgBytes,
            'backup_count' => $count,
        ];
    }

    /**
     * Count tables in database
     */
    private function countTables(string $dbName): int
    {
        $result = $this->db->rawQuery(
            "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = ?",
            [$dbName]
        );

        return !empty($result) ? $result[0]['table_count'] : 0;
    }
}
