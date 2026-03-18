# Backup & Disaster Recovery System (L4)

## Overview

The Backup & Disaster Recovery system provides comprehensive database backup management with support for multiple destination types, encryption, scheduling, and restore functionality.

### Features

- **Multiple Backup Types**: Full and incremental backups
- **Flexible Destinations**: Local filesystem, Amazon S3, SFTP, Backblaze B2
- **Encryption**: AES-256 encryption for sensitive backups
- **Scheduling**: Cron-based automated backup scheduling
- **Retention Policies**: Automatic cleanup of old backups (daily, weekly, monthly)
- **Restore Operations**: Full database restoration from any backup
- **Integrity Testing**: Verify backup files before restore
- **Health Monitoring**: Dashboard with backup status and storage metrics

## Database Schema

### backup_configs
Stores backup configuration settings per instance.

```sql
- id: bigint(20) PRIMARY KEY
- instances_id: int(11) - Instance identifier
- name: varchar(255) - Configuration name
- backup_type: enum('full','incremental') - Backup type
- schedule_cron: varchar(50) - Cron expression (optional)
- destination_type: enum('local','s3','sftp','backblaze')
- destination_config: json - Destination-specific settings
- retention_daily: int(11) - Days to keep daily backups (default: 30)
- retention_weekly: int(11) - Weeks to keep weekly backups (default: 12)
- retention_monthly: int(11) - Months to keep monthly backups (default: 12)
- encryption_enabled: boolean - AES-256 encryption toggle (default: true)
- is_active: boolean - Configuration active status
- created_at: datetime
- updated_at: datetime
```

### backup_jobs
Tracks all backup execution history.

```sql
- id: bigint(20) PRIMARY KEY
- instances_id: int(11) - Instance identifier
- backup_configs_id: bigint(20) - Reference to config
- status: enum('pending','running','completed','failed','cancelled')
- type: enum('scheduled','manual') - How backup was triggered
- started_at: datetime
- completed_at: datetime
- file_path: varchar(500) - Path to backup file
- file_size_bytes: bigint(20) - File size in bytes
- tables_count: int(11) - Number of tables backed up
- error_message: text - Error details if failed
- created_at: datetime
```

### backup_restore_log
Maintains history of restore operations.

```sql
- id: bigint(20) PRIMARY KEY
- backup_job_id: bigint(20) - Reference to backup job
- restored_by: int(11) - User ID who triggered restore
- restore_type: enum('full','partial','test')
- status: enum('started','completed','failed')
- started_at: datetime
- completed_at: datetime
- notes: text - Restore notes/results
```

## Environment Configuration

Required environment variables in `.env`:

```bash
# Database Credentials
DB_HOST=localhost
DB_USER=root
DB_PASS=password
DB_NAME=rmsdb

# Backup Paths
BACKUP_LOCAL_PATH=/var/backups/myrms/
BACKUP_TEMP_PATH=/tmp/myrms_backups/

# Encryption
BACKUP_ENCRYPTION_KEY=your-secure-key-here

# AWS S3 (optional)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_S3_BUCKET=
AWS_S3_REGION=eu-central-1

# SFTP (optional)
SFTP_HOST=
SFTP_PORT=22
SFTP_USER=
SFTP_PASSWORD=
SFTP_PATH=/backups/

# Backblaze B2 (optional)
B2_APP_ID=
B2_APP_KEY=
B2_BUCKET_ID=
```

## API Endpoints

### GET /src/api/backup/configs.php
Retrieve all backup configurations for instance.

**Response:**
```json
{
  "success": true,
  "configs": [
    {
      "id": 1,
      "name": "Daily Backup",
      "backup_type": "full",
      "destination_type": "local",
      "schedule_cron": "0 2 * * *",
      "encryption_enabled": true,
      "is_active": true
    }
  ]
}
```

### POST /src/api/backup/configs.php
Create or update backup configuration.

**Request:**
```json
{
  "id": 1,
  "name": "Daily Backup",
  "backup_type": "full",
  "destination_type": "local",
  "schedule_cron": "0 2 * * *",
  "retention_daily": 30,
  "retention_weekly": 12,
  "retention_monthly": 12,
  "encryption_enabled": true,
  "is_active": true,
  "destination_config": {
    "path": "/var/backups/myrms/"
  }
}
```

### POST /src/api/backup/run.php
Trigger manual backup.

**Request:**
```json
{
  "config_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "job_id": 123
}
```

### GET /src/api/backup/jobs.php
List backup jobs with optional filtering.

**Query Parameters:**
- `status`: Filter by status (pending, running, completed, failed, cancelled)
- `limit`: Maximum results (default: 50, max: 500)

**Response:**
```json
{
  "success": true,
  "jobs": [...],
  "count": 10
}
```

### GET /src/api/backup/health.php
Get backup system health status (for dashboard widget).

**Response:**
```json
{
  "last_backup": {...},
  "last_backup_age": "2 days 3 hours",
  "next_scheduled": "2026-03-20 02:00:00",
  "storage_used": {
    "total_bytes": 5368709120,
    "total_gb": 5.0,
    "average_bytes": 536870912,
    "backup_count": 10
  },
  "config_count": 1,
  "recent_failures": 0
}
```

### POST /src/api/backup/restore.php
Restore database from backup.

**Request:**
```json
{
  "job_id": 123,
  "confirm": true
}
```

**Important:** The `confirm` flag must be explicitly set to `true` as a safety measure. Restore operations require explicit user confirmation in the UI.

### POST /src/api/backup/test_restore.php
Test backup file integrity without restoring to live database.

**Request:**
```json
{
  "job_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "valid": true,
  "message": "Backup is valid and restorable"
}
```

### GET /src/api/backup/storage.php
Get storage consumption statistics.

**Response:**
```json
{
  "success": true,
  "total_bytes": 5368709120,
  "total_gb": 5.0,
  "average_bytes": 536870912,
  "backup_count": 10
}
```

## User Interface

### Dashboard (/src/backup/)

The backup dashboard provides:

1. **Health Status Cards**
   - Last backup time
   - Next scheduled backup
   - Storage used
   - Configuration count

2. **Backup History Table**
   - Status badges (pending, running, completed, failed, cancelled)
   - Backup type (scheduled vs manual)
   - Start/completion times
   - File size and table count
   - Restore and test actions

3. **Configuration Management**
   - Create/edit backup configurations
   - Set schedules via cron expressions
   - Configure retention policies
   - Toggle encryption
   - Manage destination settings

4. **Restore Interface**
   - List available backups
   - Test restore functionality
   - Confirm restore with safety warnings

## Service Methods

### BackupService class

```php
// Get all configurations
getConfigs(int $instanceId): array

// Save or update configuration
saveConfig(int $instanceId, array $data): int

// Trigger manual or scheduled backup
runBackup(int $configId, string $type = 'manual'): int

// Execute mysqldump
performDatabaseDump(int $jobId): bool

// Encrypt backup file (AES-256-CBC)
encryptBackup(string $filePath): string

// Upload to destination (local, S3, SFTP, Backblaze)
uploadToDestination(string $filePath, array $destConfig): string

// Get backup jobs
getJobs(int $instanceId, ?string $status = null, int $limit = 50): array

// Get single job
getJob(int $jobId): array

// Get last successful backup
getLastSuccessfulBackup(int $instanceId): ?array

// Apply retention policy
applyRetentionPolicy(int $configId): int

// List available backups
listAvailableBackups(int $instanceId): array

// Restore from backup
restoreBackup(int $jobId, int $userId): int

// Test restore integrity
testRestore(int $jobId): bool

// Get backup health status
getBackupHealth(int $instanceId): array

// Process scheduled backups (called by CRON)
processCronBackups(): void

// Calculate storage usage
calculateStorageUsed(int $instanceId): array
```

## CRON Setup

### Automated Scheduled Backups

To enable automated scheduled backups, add to system crontab:

```bash
# Run every 6 hours
0 */6 * * * /usr/bin/php /path/to/scripts/backup_cron.php >> /var/log/myrms_backup.log 2>&1

# Or run every hour for more frequent checks
0 * * * * /usr/bin/php /path/to/scripts/backup_cron.php >> /var/log/myrms_backup.log 2>&1
```

The script:
1. Finds all active backup configurations with cron schedules
2. Checks if they should run
3. Executes `performDatabaseDump()`
4. Applies retention policies
5. Logs all activities

## Permissions

Three permission levels control access:

- `BACKUP:VIEW` - View backups and health status
- `BACKUP:MANAGE` - Create/edit configurations and trigger manual backups
- `BACKUP:RESTORE` - Restore databases from backups

## Backup Destination Configurations

### Local Storage
```json
{
  "path": "/var/backups/myrms/"
}
```

### Amazon S3
```json
{
  "bucket": "my-bucket",
  "prefix": "backups/",
  "region": "eu-central-1"
}
```

### SFTP
```json
{
  "host": "backup.example.com",
  "port": 22,
  "username": "backup_user",
  "password": "encrypted_password",
  "path": "/backups/"
}
```

### Backblaze B2
```json
{
  "bucket_id": "bucket_id",
  "bucket_name": "bucket_name",
  "app_id": "app_id",
  "app_key": "encrypted_app_key"
}
```

## Encryption

### AES-256-CBC Implementation

When encryption is enabled:
1. Backup file is read into memory
2. Random 16-byte IV is generated
3. File content is encrypted using `openssl_encrypt()` with AES-256-CBC
4. IV is prepended to encrypted data
5. Encrypted file is saved with `.enc` extension

Decryption:
1. First 16 bytes are extracted as IV
2. Remaining bytes are decrypted using `openssl_decrypt()`
3. Original SQL file is reconstructed

**Note:** The encryption key should be stored securely in environment variables or a secrets management system.

## Performance Considerations

### mysqldump Options
- `--single-transaction`: Consistent snapshot without locking
- `--quick`: Minimal memory usage
- `--lock-tables=false`: Avoid table locks for concurrent access

### Backup Storage
- Maximum backup size: 10 GB (configurable)
- Temporary files stored in `/tmp/myrms_backups/`
- Final files stored in configured destination
- Old files automatically deleted per retention policy

### Database Size Impact
- Full backup: ~1-3 minutes per GB
- Incremental backup: Reduced size/time (if implemented)
- Restoration: ~2-5 minutes per GB

## Testing

### Test Restore
Use the test restore function to verify backup integrity without affecting production:

```php
$service = new BackupService($db);
if ($service->testRestore($jobId)) {
    echo "Backup is valid and restorable";
}
```

### Manual Backup
Trigger manual backup via API:

```bash
curl -X POST http://localhost/src/api/backup/run.php \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: token" \
  -d '{"config_id": 1}'
```

## Troubleshooting

### Common Issues

1. **mysqldump not found**
   - Ensure `mysql-client` package is installed
   - Check PATH includes MySQL bin directory

2. **Permission denied on backup directory**
   - Verify PHP user has write permissions: `chmod 750 /var/backups/myrms/`
   - Check parent directory ownership

3. **Backup file corrupted**
   - Use test restore to validate
   - Check available disk space during backup
   - Verify encryption/decryption key consistency

4. **Scheduled backups not running**
   - Verify crontab entry exists: `crontab -l`
   - Check logs: `tail -f /var/log/myrms_backup.log`
   - Ensure PHP and MySQL are accessible to cron user

5. **Restore fails**
   - Verify backup file exists and is readable
   - Check database user has sufficient privileges
   - Ensure database is not locked
   - Review error message in restore_log

## Migration Instructions

Run the migration to create tables:

```bash
php vendor/bin/phinx migrate -e production
```

Or use your existing migration runner:

```bash
# Check current status
php vendor/bin/phinx status -e production

# Rollback if needed
php vendor/bin/phinx rollback -e production -t 20260318240000
```

## Future Enhancements

1. **Incremental Backups**: Track changes since last backup
2. **Cloud Provider SDKs**: Native S3, Backblaze implementations
3. **Point-in-Time Recovery**: Binary log-based recovery
4. **Backup Verification**: Automatic verification after backup
5. **Alert Integration**: Email/Slack alerts for backup failures
6. **Backup Compression**: Gzip compression for storage savings
7. **Parallel Backups**: Multi-threaded backup operations
8. **Backup Metadata**: Detailed statistics and metadata tracking

## License & Support

Part of MyRMS. Contact support for implementation assistance.
