<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BackupSystem extends AbstractMigration
{
    /**
     * Change Method - Create backup system tables
     */
    public function change(): void
    {
        // Backup configurations table
        if (!$this->hasTable('backup_configs')) {
            $backupConfigs = $this->table('backup_configs', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => 'Backup configuration per instance'
            ]);

            $backupConfigs
                ->addColumn('id', 'biginteger', ['signed' => false, 'autoIncrement' => true])
                ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false, 'comment' => 'Configuration name'])
                ->addColumn('backup_type', 'enum', ['values' => ['full', 'incremental'], 'default' => 'full'])
                ->addColumn('schedule_cron', 'string', ['limit' => 50, 'null' => true, 'comment' => 'Cron expression for scheduling'])
                ->addColumn('destination_type', 'enum', ['values' => ['local', 's3', 'sftp', 'backblaze'], 'default' => 'local'])
                ->addColumn('destination_config', 'json', ['null' => true, 'comment' => 'Destination-specific config (path, bucket, credentials)'])
                ->addColumn('retention_daily', 'integer', ['default' => 30, 'comment' => 'Days to keep daily backups'])
                ->addColumn('retention_weekly', 'integer', ['default' => 12, 'comment' => 'Weeks to keep weekly backups'])
                ->addColumn('retention_monthly', 'integer', ['default' => 12, 'comment' => 'Months to keep monthly backups'])
                ->addColumn('encryption_enabled', 'boolean', ['default' => true, 'comment' => 'Enable AES-256 encryption'])
                ->addColumn('is_active', 'boolean', ['default' => true, 'comment' => 'Configuration is active'])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->create();
        }

        // Backup jobs table
        if (!$this->hasTable('backup_jobs')) {
            $backupJobs = $this->table('backup_jobs', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => 'Backup job execution history'
            ]);

            $backupJobs
                ->addColumn('id', 'biginteger', ['signed' => false, 'autoIncrement' => true])
                ->addColumn('instances_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('backup_configs_id', 'biginteger', ['signed' => false, 'null' => true])
                ->addColumn('status', 'enum', ['values' => ['pending', 'running', 'completed', 'failed', 'cancelled'], 'default' => 'pending'])
                ->addColumn('type', 'enum', ['values' => ['scheduled', 'manual'], 'default' => 'manual', 'comment' => 'How backup was triggered'])
                ->addColumn('started_at', 'datetime', ['null' => true])
                ->addColumn('completed_at', 'datetime', ['null' => true])
                ->addColumn('file_path', 'string', ['limit' => 500, 'null' => true, 'comment' => 'Path to backup file'])
                ->addColumn('file_size_bytes', 'biginteger', ['signed' => false, 'null' => true])
                ->addColumn('tables_count', 'integer', ['null' => true, 'comment' => 'Number of tables backed up'])
                ->addColumn('error_message', 'text', ['null' => true, 'comment' => 'Error details if failed'])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->addIndex(['backup_configs_id'])
                ->addIndex(['status'])
                ->create();
        }

        // Backup restore log table
        if (!$this->hasTable('backup_restore_log')) {
            $backupRestoreLog = $this->table('backup_restore_log', [
                'id' => false,
                'primary_key' => ['id'],
                'engine' => 'InnoDB',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => 'Backup restore operations history'
            ]);

            $backupRestoreLog
                ->addColumn('id', 'biginteger', ['signed' => false, 'autoIncrement' => true])
                ->addColumn('backup_job_id', 'biginteger', ['signed' => false, 'null' => false])
                ->addColumn('restored_by', 'integer', ['signed' => false, 'null' => false, 'comment' => 'User ID who triggered restore'])
                ->addColumn('restore_type', 'enum', ['values' => ['full', 'partial', 'test'], 'default' => 'full'])
                ->addColumn('status', 'enum', ['values' => ['started', 'completed', 'failed'], 'default' => 'started'])
                ->addColumn('started_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('completed_at', 'datetime', ['null' => true])
                ->addColumn('notes', 'text', ['null' => true, 'comment' => 'Restore notes/results'])
                ->addIndex(['backup_job_id'])
                ->addIndex(['restored_by'])
                ->create();
        }
    }
}
