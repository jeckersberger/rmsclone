# Backup & Disaster Recovery - Quick Start Guide

## ⚡ 5-Minute Setup

### 1. Run Migration
```bash
cd /sessions/vibrant-kind-edison/rmsclone
vendor/bin/phinx migrate -e production
```

Creates 3 new tables:
- `backup_configs` - Backup settings
- `backup_jobs` - Execution history
- `backup_restore_log` - Restore operations

### 2. Configure Environment (.env)
```bash
# Database (usually already set)
DB_HOST=localhost
DB_USER=root
DB_PASS=password
DB_NAME=rmsdb

# Backup paths
BACKUP_LOCAL_PATH=/var/backups/myrms/
BACKUP_TEMP_PATH=/tmp/myrms_backups/

# Encryption
BACKUP_ENCRYPTION_KEY=your-secure-key-here
```

### 3. Create Directories
```bash
sudo mkdir -p /var/backups/myrms/
sudo mkdir -p /tmp/myrms_backups/
sudo chown www-data:www-data /var/backups/myrms/
sudo chmod 750 /var/backups/myrms/
```

### 4. Access Dashboard
Navigate to: `http://yoursite/src/backup/`

You'll see:
- Health status cards
- Backup history
- Configuration options
- Storage usage

### 5. Create First Backup Config
Click "Konfiguration" button:
- Name: "Daily Backup"
- Backup Type: Full
- Destination: Local
- Schedule: `0 2 * * *` (2 AM daily)
- Retention: 30 days (daily), 12 weeks, 12 months
- Enable encryption: ✓

Click "Speichern"

### 6. Run Manual Backup
Click "Jetzt Backup erstellen" button → Select config → "Backup starten"

Wait 1-5 minutes (depends on DB size).

### 7. Enable CRON
```bash
# Add to crontab
crontab -e

# Add this line (every 6 hours):
0 */6 * * * /usr/bin/php /path/to/scripts/backup_cron.php >> /var/log/myrms_backup.log 2>&1
```

---

## 📊 Dashboard Overview

### Health Cards
- **Last Backup**: Age of most recent successful backup
- **Next Scheduled**: When next backup will run
- **Storage Used**: Total GB consumed by backups
- **Configurations**: Active backup configs

### Backup History Table
- Status badges: pending → running → completed/failed
- Manual vs scheduled backups
- File size and table count
- Actions: Restore, Test, View errors

### Configurations Section
- List all active configs
- Shows: type, destination, schedule, retention
- Edit button for each config

---

## 🔄 Common Operations

### Manual Backup Now
```
Dashboard → "Jetzt Backup erstellen" → Select config → Start
```

### Restore from Backup
```
Dashboard → Find backup in history → "Restore" button
→ Review warning modal → Check confirmation checkbox → "Wiederherstellen"
```

### Test Backup Integrity
```
Dashboard → Find backup → "Testen" button
→ Verifies file without touching production DB
```

### Update Configuration
```
Dashboard → "Konfiguration" → Edit form → "Speichern"
```

### View Storage Usage
```
Dashboard → "Speicher genutzt" card
Or API: GET /src/api/backup/storage.php
```

---

## 🔐 Permissions Required

Grant to users who should manage backups:
- **View**: `BACKUP:VIEW`
- **Manage**: `BACKUP:MANAGE` (create configs, run backups)
- **Restore**: `BACKUP:RESTORE` (restore from backups)

---

## 📍 File Locations

| Component | Path |
|-----------|------|
| Migration | `db/migrations/20260318240000_backup_system.php` |
| Service | `src/services/BackupService.php` |
| Controller | `src/backup/index.php` |
| Template | `src/backup/backup_index.twig` |
| API Endpoints | `src/api/backup/` (7 files) |
| CRON Script | `scripts/backup_cron.php` |
| Docs | `docs/BACKUP_SYSTEM.md` |

---

## 🧪 Testing Checklist

- [ ] Migration ran without errors
- [ ] Can access `/src/backup/` dashboard
- [ ] Created a backup config
- [ ] Triggered manual backup
- [ ] Backup appears in history
- [ ] Can run test restore
- [ ] Storage metrics display correctly
- [ ] CRON script logs successful runs
- [ ] Restore works without errors

---

## 🚨 Troubleshooting

### "mysqldump not found"
```bash
# Install MySQL client
apt-get install mysql-client
```

### "Permission denied" on backup directory
```bash
chmod 750 /var/backups/myrms/
chown www-data:www-data /var/backups/myrms/
```

### Scheduled backups not running
```bash
# Check crontab
crontab -l

# Check logs
tail -f /var/log/myrms_backup.log

# Manual test
php scripts/backup_cron.php
```

### Restore fails
- Verify backup file exists and is readable
- Check if database is locked
- Review error message in dashboard
- Test with test_restore first

---

## 📈 Performance Notes

- **Full Backup**: ~1-3 min per GB
- **Encryption**: Adds ~10-20% overhead
- **Restore**: ~2-5 min per GB
- **Storage**: Keep in SSD for best performance

---

## 🔗 API Quick Reference

### Get Configs
```bash
GET /src/api/backup/configs.php
```

### Create Backup
```bash
POST /src/api/backup/run.php
{"config_id": 1}
```

### List Jobs
```bash
GET /src/api/backup/jobs.php?status=completed&limit=50
```

### Health Status
```bash
GET /src/api/backup/health.php
```

### Storage Usage
```bash
GET /src/api/backup/storage.php
```

### Test Restore
```bash
POST /src/api/backup/test_restore.php
{"job_id": 123}
```

### Restore Database
```bash
POST /src/api/backup/restore.php
{"job_id": 123, "confirm": true}
```

---

## 📞 Support

For issues, see: `docs/BACKUP_SYSTEM.md` (full documentation)

For implementation help, contact support.

---

**Status**: ✅ Ready to Deploy

Run migration and start backing up! 🎉
