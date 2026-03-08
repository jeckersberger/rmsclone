#!/usr/bin/env bash
# ==============================================================================
# AdamRMS - Log Rotation Setup
# ==============================================================================
#
# Installs logrotate configuration for:
#   - PHP error logs
#   - AdamRMS application / cron logs
#
# Policy: weekly rotation, keep 12 weeks, compress old logs.
#
# Usage (run as root):
#   sudo ./scripts/setup-logrotate.sh
# ==============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Check permissions
# ---------------------------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
    echo "[ERROR] This script must be run as root (sudo)." >&2
    exit 1
fi

LOGROTATE_DIR="/etc/logrotate.d"

if [[ ! -d "$LOGROTATE_DIR" ]]; then
    echo "[ERROR] logrotate configuration directory not found: $LOGROTATE_DIR" >&2
    echo "        Install logrotate first: apt-get install logrotate" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# 1. PHP error logs
# ---------------------------------------------------------------------------
cat > "$LOGROTATE_DIR/adamrms-php" <<'EOF'
/var/log/php_errors.log
/var/log/php-fpm/*.log
{
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        # Signal PHP-FPM to reopen log files (if running)
        [ -f /var/run/php-fpm.pid ] && kill -USR1 $(cat /var/run/php-fpm.pid) 2>/dev/null || true
    endscript
}
EOF

echo "[OK] Installed: $LOGROTATE_DIR/adamrms-php"

# ---------------------------------------------------------------------------
# 2. Application / cron logs
# ---------------------------------------------------------------------------
cat > "$LOGROTATE_DIR/adamrms-app" <<'EOF'
/var/log/adamrms-*.log
/var/log/adamrms/*.log
{
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data adm
}
EOF

echo "[OK] Installed: $LOGROTATE_DIR/adamrms-app"

# ---------------------------------------------------------------------------
# Verify configuration
# ---------------------------------------------------------------------------
echo ""
echo "Verifying logrotate configuration..."
if logrotate --debug "$LOGROTATE_DIR/adamrms-php" 2>&1 | head -5; then
    echo "[OK] PHP log rotation config is valid"
else
    echo "[WARN] PHP log rotation config may have issues"
fi

if logrotate --debug "$LOGROTATE_DIR/adamrms-app" 2>&1 | head -5; then
    echo "[OK] App log rotation config is valid"
else
    echo "[WARN] App log rotation config may have issues"
fi

echo ""
echo "Log rotation setup complete."
echo "  - Rotation:    weekly"
echo "  - Retention:   12 weeks"
echo "  - Compression: enabled (delayed by 1 cycle)"
