#!/usr/bin/env bash
# ==============================================================================
# AdamRMS - Database Restore Script
# ==============================================================================
#
# Restores a database from a compressed backup file created by backup-db.sh.
#
# Usage:
#   ./scripts/restore-db.sh <backup-file.sql.gz>
#
# The script will prompt for confirmation before overwriting the database.
#
# Environment variables (or sourced from .env.prod):
#   DB_HOSTNAME  - MySQL host (default: db)
#   DB_DATABASE  - Database name (default: adamrms)
#   DB_USERNAME  - MySQL user (default: adamrms)
#   DB_PASSWORD  - MySQL password (required)
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

# ---------------------------------------------------------------------------
# Validate arguments
# ---------------------------------------------------------------------------
if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <backup-file.sql.gz>"
    echo ""
    echo "Available backups:"
    ls -lh "$PROJECT_DIR/backups"/backup-*.sql.gz 2>/dev/null || echo "  (none found in $PROJECT_DIR/backups/)"
    exit 1
fi

BACKUP_FILE="$1"

if [[ ! -f "$BACKUP_FILE" ]]; then
    echo "[ERROR] Backup file not found: $BACKUP_FILE" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Safety confirmation
# ---------------------------------------------------------------------------
echo "============================================================"
echo "  WARNING: DATABASE RESTORE"
echo "============================================================"
echo ""
echo "  This will OVERWRITE all data in database '$DB_NAME'"
echo "  on host '$DB_HOST' with the contents of:"
echo ""
echo "    $BACKUP_FILE"
echo ""
echo "  This action CANNOT be undone."
echo ""

# Allow non-interactive mode with RESTORE_CONFIRM=yes
if [[ "${RESTORE_CONFIRM:-}" != "yes" ]]; then
    read -rp "  Type 'yes' to continue: " CONFIRM
    if [[ "$CONFIRM" != "yes" ]]; then
        echo "Restore cancelled."
        exit 0
    fi
fi

echo ""
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting restore from: $(basename "$BACKUP_FILE")"

# ---------------------------------------------------------------------------
# Decompress and import
# ---------------------------------------------------------------------------
if [[ "$BACKUP_FILE" == *.gz ]]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Decompressing and importing..."
    gunzip -c "$BACKUP_FILE" | mysql \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_USER" \
        --password="$DB_PASS" \
        "$DB_NAME"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Importing (uncompressed)..."
    mysql \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_USER" \
        --password="$DB_PASS" \
        "$DB_NAME" < "$BACKUP_FILE"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restore completed successfully"
exit 0
