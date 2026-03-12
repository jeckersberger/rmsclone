#!/bin/sh
# ==============================================================================
# MyRMS - Synology NAS Installer
# ==============================================================================
# Direkt auf dem NAS ausfuehren:
#   sudo sh install-nas.sh
# ==============================================================================

set -e

INSTALL_DIR="/volume3/docker/rmsclone"
DOMAIN="dash.je-soundulight"
TUNNEL_TOKEN="eyJhIjoiN2QzNzM4MTdhNTYyMmY4ZmVlNDdlM2VjNTFlOGRjY2YiLCJ0IjoiY2Q1ZGNkYmYtYzQwYy00YzlmLWFiOGEtZWJiNmM0NzllMTVhIiwicyI6Ik5EaGxOREZoT1dRdE1tUTFOUzAwWXpJeUxXSXpZalF0TW1NeE5qTTNaVGs0WkRGaiJ9"
REPO_ZIP="https://github.com/jeckersberger/rmsclone/archive/refs/heads/main.zip"

echo ""
echo "============================================="
echo "  MyRMS Installer - Synology NAS"
echo "  Ziel: https://${DOMAIN}"
echo "  Pfad: ${INSTALL_DIR}"
echo "============================================="
echo ""

# ------------------------------------------------------------------
# 1. Passwoerter generieren
# ------------------------------------------------------------------
echo "[1/5] Generiere sichere Passwoerter..."

# Synology hat kein openssl rand manchmal, Fallback mit /dev/urandom
DB_PASS=$(head -c 48 /dev/urandom | od -An -tx1 | tr -d ' \n' | head -c 32)
ROOT_PASS=$(head -c 48 /dev/urandom | od -An -tx1 | tr -d ' \n' | head -c 32)
JWT_KEY=$(head -c 48 /dev/urandom | od -An -tx1 | tr -d ' \n' | head -c 64)

echo "  OK - Passwoerter generiert"

# ------------------------------------------------------------------
# 2. Repo herunterladen
# ------------------------------------------------------------------
echo ""
echo "[2/5] Lade Projekt herunter..."

mkdir -p "${INSTALL_DIR}"
cd /tmp

# Download als ZIP (kein git noetig)
if command -v wget > /dev/null 2>&1; then
    wget -q -O rmsclone.zip "${REPO_ZIP}"
elif command -v curl > /dev/null 2>&1; then
    curl -sL -o rmsclone.zip "${REPO_ZIP}"
else
    echo "FEHLER: Weder wget noch curl gefunden!"
    exit 1
fi

# Entpacken
if command -v unzip > /dev/null 2>&1; then
    unzip -qo rmsclone.zip
else
    # Synology hat manchmal 7z statt unzip
    echo "FEHLER: unzip nicht gefunden. Installiere das SynoCommunity-Paket 'unzip'."
    exit 1
fi

# Dateien kopieren (ueberschreibt bestehende)
cp -r /tmp/rmsclone-main/* "${INSTALL_DIR}/"
cp -r /tmp/rmsclone-main/.* "${INSTALL_DIR}/" 2>/dev/null || true

# Aufraeumen
rm -rf /tmp/rmsclone.zip /tmp/rmsclone-main

echo "  OK - Projekt nach ${INSTALL_DIR} kopiert"

# ------------------------------------------------------------------
# 3. .env Datei erstellen
# ------------------------------------------------------------------
echo ""
echo "[3/5] Erstelle Konfiguration..."

cat > "${INSTALL_DIR}/.env" <<EOF
# MyRMS - Synology Cloudflare Tunnel
# Generiert am: $(date '+%Y-%m-%d %H:%M:%S')

TUNNEL_TOKEN=${TUNNEL_TOKEN}
DB_PASSWORD=${DB_PASS}
MYSQL_ROOT_PASSWORD=${ROOT_PASS}
JWT_KEY=${JWT_KEY}
EOF

chmod 600 "${INSTALL_DIR}/.env"
echo "  OK - .env erstellt"

# ------------------------------------------------------------------
# 4. Zugangsdaten speichern
# ------------------------------------------------------------------
cat > "${INSTALL_DIR}/myrms-zugangsdaten.txt" <<EOF
==============================================================================
MyRMS - Zugangsdaten
==============================================================================
Erstellt am:  $(date '+%Y-%m-%d %H:%M:%S')
URL:          https://${DOMAIN}
Pfad:         ${INSTALL_DIR}
==============================================================================

Datenbank:
  Host:       db (intern im Docker-Netzwerk)
  Datenbank:  myrms
  User:       myrms
  Passwort:   ${DB_PASS}
  Root-PW:    ${ROOT_PASS}
  Port:       3306

JWT Secret Key:
  ${JWT_KEY}

Cloudflare Tunnel Token:
  ${TUNNEL_TOKEN}

Befehle (als root/sudo):
  Status:     cd ${INSTALL_DIR} && sudo docker-compose -f docker-compose.synology-cloudflare.yml ps
  Logs:       cd ${INSTALL_DIR} && sudo docker-compose -f docker-compose.synology-cloudflare.yml logs -f
  Neustart:   cd ${INSTALL_DIR} && sudo docker-compose -f docker-compose.synology-cloudflare.yml restart
  Stoppen:    cd ${INSTALL_DIR} && sudo docker-compose -f docker-compose.synology-cloudflare.yml down
==============================================================================
EOF

chmod 600 "${INSTALL_DIR}/myrms-zugangsdaten.txt"
echo "  OK - Zugangsdaten gespeichert: ${INSTALL_DIR}/myrms-zugangsdaten.txt"

# ------------------------------------------------------------------
# 5. Docker bauen & starten
# ------------------------------------------------------------------
echo ""
echo "[4/5] Baue Docker Images (das kann einige Minuten dauern)..."

cd "${INSTALL_DIR}"
docker-compose -f docker-compose.synology-cloudflare.yml build

echo "  OK - Images gebaut"

echo ""
echo "[5/5] Starte Stack..."

docker-compose -f docker-compose.synology-cloudflare.yml down 2>/dev/null || true
docker-compose -f docker-compose.synology-cloudflare.yml up -d

echo "  OK - Stack gestartet"

# ------------------------------------------------------------------
# Ergebnis
# ------------------------------------------------------------------
echo ""
sleep 5
echo "Container Status:"
docker-compose -f docker-compose.synology-cloudflare.yml ps

echo ""
echo "============================================="
echo "  Installation abgeschlossen!"
echo "============================================="
echo ""
echo "  URL:          https://${DOMAIN}"
echo "  Zugangsdaten: ${INSTALL_DIR}/myrms-zugangsdaten.txt"
echo ""
echo "  WICHTIG: Konfiguriere den Public Hostname"
echo "  in Cloudflare Zero Trust:"
echo "    1. https://one.dash.cloudflare.com"
echo "    2. Networks -> Tunnels -> AdamRMS -> Configure"
echo "    3. Public Hostname -> Add"
echo "    4. Subdomain: dash"
echo "    5. Domain:    je-soundulight"
echo "    6. Type:      HTTP"
echo "    7. URL:       nginx:80"
echo ""
