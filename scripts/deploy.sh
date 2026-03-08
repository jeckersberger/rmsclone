#!/usr/bin/env bash
# ==============================================================================
# AdamRMS - Deployment Script
# ==============================================================================
#
# Performs a complete deployment:
#   1. Pull latest code from git
#   2. Install/update Composer dependencies
#   3. Run Phinx database migrations
#   4. Clear OPcache
#   5. Run health check
#   6. Print deployment summary
#
# Usage:
#   ./scripts/deploy.sh [--skip-pull] [--skip-opcache]
#
# Options:
#   --skip-pull     Skip git pull (useful in CI/CD where code is already checked out)
#   --skip-opcache  Skip OPcache reset (when running outside the web server)
#
# Exit codes:
#   0 - Success
#   1 - A deployment step failed
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DEPLOY_START=$(date +%s)

# Parse arguments
SKIP_PULL=false
SKIP_OPCACHE=false
for arg in "$@"; do
    case "$arg" in
        --skip-pull)    SKIP_PULL=true ;;
        --skip-opcache) SKIP_OPCACHE=true ;;
    esac
done

echo "============================================================"
echo "  AdamRMS Deployment"
echo "  Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "  Directory: $PROJECT_DIR"
echo "============================================================"
echo ""

cd "$PROJECT_DIR"

STEPS_TOTAL=5
STEP=0
ERRORS=0

# ---------------------------------------------------------------------------
# 1. Pull latest code
# ---------------------------------------------------------------------------
STEP=$((STEP + 1))
echo "[$STEP/$STEPS_TOTAL] Pulling latest code..."
if [[ "$SKIP_PULL" == "true" ]]; then
    echo "  Skipped (--skip-pull)"
else
    if git pull --ff-only 2>&1; then
        GIT_REV=$(git rev-parse --short HEAD)
        echo "  Current revision: $GIT_REV"
    else
        echo "  [ERROR] git pull failed. Resolve conflicts manually." >&2
        ERRORS=$((ERRORS + 1))
    fi
fi
echo ""

# ---------------------------------------------------------------------------
# 2. Install Composer dependencies
# ---------------------------------------------------------------------------
STEP=$((STEP + 1))
echo "[$STEP/$STEPS_TOTAL] Installing Composer dependencies..."
if composer install --no-dev --no-interaction --optimize-autoloader 2>&1; then
    echo "  Dependencies installed"
else
    echo "  [ERROR] composer install failed" >&2
    ERRORS=$((ERRORS + 1))
fi
echo ""

# ---------------------------------------------------------------------------
# 3. Run database migrations
# ---------------------------------------------------------------------------
STEP=$((STEP + 1))
echo "[$STEP/$STEPS_TOTAL] Running database migrations..."
if php vendor/bin/phinx migrate -e production 2>&1; then
    echo "  Migrations applied"
else
    echo "  [ERROR] Phinx migrations failed" >&2
    ERRORS=$((ERRORS + 1))
fi
echo ""

# ---------------------------------------------------------------------------
# 4. Clear OPcache
# ---------------------------------------------------------------------------
STEP=$((STEP + 1))
echo "[$STEP/$STEPS_TOTAL] Clearing OPcache..."
if [[ "$SKIP_OPCACHE" == "true" ]]; then
    echo "  Skipped (--skip-opcache)"
else
    # Method 1: Use the web server to reset OPcache via a temporary script
    OPCACHE_RESET_FILE="$PROJECT_DIR/src/opcache-reset-$(date +%s).php"
    cat > "$OPCACHE_RESET_FILE" <<'PHPEOF'
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared";
} else {
    echo "OPcache not available";
}
unlink(__FILE__);
PHPEOF

    # Try to hit the reset endpoint
    ROOT_URL="${ROOT_URL:-http://localhost:80}"
    RESET_FILENAME=$(basename "$OPCACHE_RESET_FILE")
    RESPONSE=$(curl -sf --max-time 10 "${ROOT_URL}/${RESET_FILENAME}" 2>/dev/null || true)

    if [[ "$RESPONSE" == *"OPcache"* ]]; then
        echo "  $RESPONSE"
    else
        # Cleanup the temp file if curl failed
        rm -f "$OPCACHE_RESET_FILE"
        echo "  Could not reach web server to reset OPcache (non-fatal)"
    fi
fi
echo ""

# ---------------------------------------------------------------------------
# 5. Health check
# ---------------------------------------------------------------------------
STEP=$((STEP + 1))
echo "[$STEP/$STEPS_TOTAL] Running health check..."
HEALTH_URL="${ROOT_URL:-http://localhost:80}/api/health.php"

HEALTH_RESPONSE=$(curl -sf --max-time 15 "$HEALTH_URL" 2>/dev/null || true)
if [[ -n "$HEALTH_RESPONSE" ]]; then
    HEALTH_STATUS=$(echo "$HEALTH_RESPONSE" | php -r 'echo json_decode(file_get_contents("php://stdin"))->status ?? "unknown";' 2>/dev/null || echo "unknown")
    if [[ "$HEALTH_STATUS" == "ok" ]]; then
        echo "  Health check: PASSED"
    else
        echo "  Health check: FAILED (status: $HEALTH_STATUS)" >&2
        echo "  Response: $HEALTH_RESPONSE"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo "  Health check: Could not reach $HEALTH_URL (non-fatal)"
fi
echo ""

# ---------------------------------------------------------------------------
# Deployment Summary
# ---------------------------------------------------------------------------
DEPLOY_END=$(date +%s)
DEPLOY_DURATION=$((DEPLOY_END - DEPLOY_START))

echo "============================================================"
echo "  Deployment Summary"
echo "============================================================"
echo "  Duration:   ${DEPLOY_DURATION}s"
echo "  Revision:   ${GIT_REV:-unknown}"
echo "  Errors:     $ERRORS"

if [[ $ERRORS -gt 0 ]]; then
    echo "  Status:     FAILED"
    echo "============================================================"
    exit 1
else
    echo "  Status:     SUCCESS"
    echo "============================================================"
    exit 0
fi
