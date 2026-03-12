#!/usr/bin/env bash
# ==============================================================================
# AdamRMS - Database Backup Script
# ==============================================================================
#
# Creates a compressed mysqldump backup with timestamp, manages retention,
# and optionally uploads to S3.
#
# Usage:
#   ./scripts/backup-db.sh
#
# Environment variables (or sourced from .env.prod):
#   DB_HOSTNAME          - MySQL host (default: db)
#   DB_DATABASE          - Database name (default: adamrms)
#   DB_USERNAME          - MySQL user (default: adamrms)
#   DB_PASSWORD          - MySQL password (required)
#   BACKUP_DIR           - Local backup directory (default: ./backups)
#   BACKUP_RETENTION_DAYS- Days to keep backups (default: 30)
#   BACKUP_S3_BUCKET     - S3 bucket for off-site copy (optional)
#   BACKUP_S3_REGION     - AWS region for S3 (optional)
#
# Exit codes:
#   0 - Success
#   1 - mysqldump failed
#   2 - Compression failed
#   3 - S3 upload failed
#
# Recommended crontab entry (daily at 02:00):
#   0 2 * * * /path/to/scripts/backup-db.sh >> /var/log/adamrms-backup.log 2>&1
# ==============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Source .env.prod if it exists
if [[ -f "$PROJECT_DIR/.env.prod" ]]; then
    # shellcheck disable=SC1091
    set -a; source "$PROJECT_DIR/.env.prod"; set +a
fi

DB_HOST="${DB_HOSTNAME:-db}"
DB_NAME="${DB_DATABASE:-adamrms}"
DB_USER="${DB_USERNAME:-adamrms}"
DB_PASS="${DB_PASSWORD:?ERROR: DB_PASSWORD is not set}"
DB_PORT="${DB_PORT:-3306}"

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
TIMESTAMP="$(date +%Y-%m-%d-%H%M%S)"
BACKUP_FILE="backup-${TIMESTAMP}.sql.gz"

# ---------------------------------------------------------------------------
# Ensure backup directory exists
# ---------------------------------------------------------------------------
mkdir -p "$BACKUP_DIR"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting database backup..."
echo "  Host:      $DB_HOST"
echo "  Database:  $DB_NAME"
echo "  Output:    $BACKUP_DIR/$BACKUP_FILE"

# ---------------------------------------------------------------------------
# 1. Create the dump
# ---------------------------------------------------------------------------
if ! mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --quick \
    --lock-tables=false \
    "$DB_NAME" | gzip > "$BACKUP_DIR/$BACKUP_FILE"; then
    echo "[ERROR] mysqldump failed" >&2
    rm -f "$BACKUP_DIR/$BACKUP_FILE"
    exit 1
fi

FILESIZE=$(du -h "$BACKUP_DIR/$BACKUP_FILE" | cut -f1)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup created: $BACKUP_FILE ($FILESIZE)"

# ---------------------------------------------------------------------------
# 2. Delete old backups beyond retention period
# ---------------------------------------------------------------------------
DELETED=0
while IFS= read -r -d '' old_backup; do
    rm -f "$old_backup"
    DELETED=$((DELETED + 1))
    echo "  Deleted old backup: $(basename "$old_backup")"
done < <(find "$BACKUP_DIR" -name 'backup-*.sql.gz' -mtime +"$RETENTION_DAYS" -print0 2>/dev/null)

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Retention cleanup: $DELETED old backup(s) removed (keeping $RETENTION_DAYS days)"

# ---------------------------------------------------------------------------
# 3. Optional: Upload to S3
# ---------------------------------------------------------------------------
if [[ -n "${BACKUP_S3_BUCKET:-}" ]]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Uploading to S3: s3://$BACKUP_S3_BUCKET/$BACKUP_FILE"

    S3_ARGS=()
    if [[ -n "${BACKUP_S3_REGION:-}" ]]; then
        S3_ARGS+=(--region "$BACKUP_S3_REGION")
    fi

    if aws s3 cp "$BACKUP_DIR/$BACKUP_FILE" "s3://$BACKUP_S3_BUCKET/$BACKUP_FILE" "${S3_ARGS[@]}"; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] S3 upload complete"
    else
        echo "[ERROR] S3 upload failed" >&2
        exit 3
    fi
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup finished successfully"
exit 0
