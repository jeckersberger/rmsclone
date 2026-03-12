#!/bin/sh
# ==============================================================================
# MyRMS - Synology NAS Updater
# ==============================================================================
# Direkt auf dem NAS ausfuehren:
#   cd /volume3/docker/rmsclone && sudo sh update-nas.sh
#
# Oder mit explizitem Token:
#   sudo sh update-nas.sh ghp_XXXXXXXXXXXX
# ==============================================================================

set -e

INSTALL_DIR="/volume3/docker/rmsclone"
GITHUB_REPO="jeckersberger/rmsclone"
COMPOSE_FILE="docker-compose.synology-cloudflare.yml"

# Token aus Argument oder gespeicherter Datei
if [ -n "${1:-}" ]; then
    GITHUB_TOKEN="$1"
elif [ -f "${INSTALL_DIR}/.github-token" ]; then
    GITHUB_TOKEN=$(cat "${INSTALL_DIR}/.github-token")
else
    GITHUB_TOKEN=""
fi

echo ""
echo "============================================="
echo "  MyRMS Updater - Synology NAS"
echo "============================================="
echo ""

# ------------------------------------------------------------------
# 1. Neue Version herunterladen
# ------------------------------------------------------------------
echo "[1/3] Lade neueste Version herunter..."

cd /tmp

if [ -n "${GITHUB_TOKEN}" ]; then
    REPO_URL="https://api.github.com/repos/${GITHUB_REPO}/tarball/main"
    AUTH_HEADER="Authorization: token ${GITHUB_TOKEN}"
    if command -v curl > /dev/null 2>&1; then
        curl -sL -H "${AUTH_HEADER}" -o rmsclone-update.tar.gz "${REPO_URL}"
    elif command -v wget > /dev/null 2>&1; then
        wget -q --header="${AUTH_HEADER}" -O rmsclone-update.tar.gz "${REPO_URL}"
    fi
else
    REPO_URL="https://github.com/${GITHUB_REPO}/archive/refs/heads/main.tar.gz"
    if command -v curl > /dev/null 2>&1; then
        curl -sL -o rmsclone-update.tar.gz "${REPO_URL}"
    elif command -v wget > /dev/null 2>&1; then
        wget -q -O rmsclone-update.tar.gz "${REPO_URL}"
    fi
fi

mkdir -p /tmp/rmsclone-update
tar xzf rmsclone-update.tar.gz -C /tmp/rmsclone-update --strip-components=1

echo "  OK - Heruntergeladen"

# ------------------------------------------------------------------
# 2. Dateien aktualisieren (ohne .env und Zugangsdaten)
# ------------------------------------------------------------------
echo ""
echo "[2/3] Aktualisiere Dateien..."

# .env und Zugangsdaten sichern
cp "${INSTALL_DIR}/.env" /tmp/.env.backup 2>/dev/null || true
cp "${INSTALL_DIR}/myrms-zugangsdaten.txt" /tmp/myrms-zugangsdaten.backup 2>/dev/null || true
cp "${INSTALL_DIR}/.github-token" /tmp/.github-token.backup 2>/dev/null || true

# Neue Dateien kopieren
cp -r /tmp/rmsclone-update/* "${INSTALL_DIR}/"
cp -r /tmp/rmsclone-update/.* "${INSTALL_DIR}/" 2>/dev/null || true

# Gesicherte Dateien wiederherstellen
cp /tmp/.env.backup "${INSTALL_DIR}/.env" 2>/dev/null || true
cp /tmp/myrms-zugangsdaten.backup "${INSTALL_DIR}/myrms-zugangsdaten.txt" 2>/dev/null || true
cp /tmp/.github-token.backup "${INSTALL_DIR}/.github-token" 2>/dev/null || true

# Aufraeumen
rm -rf /tmp/rmsclone-update.tar.gz /tmp/rmsclone-update
rm -f /tmp/.env.backup /tmp/myrms-zugangsdaten.backup /tmp/.github-token.backup

echo "  OK - Dateien aktualisiert"

# ------------------------------------------------------------------
# 3. Docker neu bauen & starten
# ------------------------------------------------------------------
echo ""
echo "[3/3] Baue und starte Docker Container neu..."

cd "${INSTALL_DIR}"
docker-compose -f "${COMPOSE_FILE}" build
docker-compose -f "${COMPOSE_FILE}" down 2>/dev/null || true
docker-compose -f "${COMPOSE_FILE}" up -d

echo "  OK - Stack neu gestartet"

echo ""
sleep 5
echo "Container Status:"
docker-compose -f "${COMPOSE_FILE}" ps

echo ""
echo "============================================="
echo "  Update abgeschlossen!"
echo "============================================="
echo ""
