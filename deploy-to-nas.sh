#!/usr/bin/env bash
# ==============================================================================
# MyRMS - Deploy auf NAS via SSH
# ==============================================================================
# Dieses Script führst du auf deinem LOKALEN Rechner aus.
# Es verbindet sich per SSH zum NAS und richtet alles automatisch ein.
# Die Zugangsdaten werden lokal in myrms-zugangsdaten.txt gespeichert.
#
# Verwendung:
#   bash deploy-to-nas.sh
# ==============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CREDENTIALS_FILE="$SCRIPT_DIR/myrms-zugangsdaten.txt"
DOMAIN="dash.je-soundulight"

echo -e "${CYAN}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  MyRMS - Deploy auf NAS via SSH                         ║"
echo "║  Ziel: https://${DOMAIN}                    ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# ==========================================================================
# 1. NAS Verbindungsdaten & Tunnel Token abfragen
# ==========================================================================
echo -e "${BOLD}[1/7] Verbindungsdaten${NC}"
read -rp "  NAS IP-Adresse oder Hostname: " NAS_HOST
read -rp "  SSH-User (z.B. admin oder root): " NAS_USER
read -rp "  SSH-Port [22]: " NAS_PORT
NAS_PORT=${NAS_PORT:-22}
read -rp "  Installationspfad auf dem NAS [/volume1/docker/rmsclone]: " NAS_PATH
NAS_PATH=${NAS_PATH:-/volume1/docker/rmsclone}

echo ""
echo -e "${BOLD}Cloudflare Tunnel Token${NC}"
echo -e "  (Findest du unter: https://one.dash.cloudflare.com → Zero Trust → Networks → Tunnels)"
echo -e "  (Tunnel anklicken → Configure → Install connector → Token kopieren)"
read -rp "  Tunnel Token: " TUNNEL_TOKEN

if [ -z "$TUNNEL_TOKEN" ]; then
    echo -e "${RED}  Kein Token eingegeben! Abbruch.${NC}"
    exit 1
fi

SSH_CMD="ssh -p ${NAS_PORT} ${NAS_USER}@${NAS_HOST}"
SCP_CMD="scp -P ${NAS_PORT}"

# ==========================================================================
# 2. SSH-Verbindung testen
# ==========================================================================
echo ""
echo -e "${BOLD}[2/7] SSH-Verbindung testen...${NC}"
if ! $SSH_CMD "echo 'OK'" 2>/dev/null; then
    echo -e "${RED}  SSH-Verbindung fehlgeschlagen!${NC}"
    echo "  Prüfe: ssh -p ${NAS_PORT} ${NAS_USER}@${NAS_HOST}"
    exit 1
fi
echo -e "${GREEN}  ✓ SSH-Verbindung erfolgreich${NC}"

# ==========================================================================
# 3. Docker prüfen
# ==========================================================================
echo ""
echo -e "${BOLD}[3/7] Docker auf NAS prüfen...${NC}"
if ! $SSH_CMD "docker --version" 2>/dev/null; then
    echo -e "${RED}  Docker ist nicht installiert!${NC}"
    echo "  Installiere Docker über das Synology Paket-Zentrum (Container Manager)."
    exit 1
fi
echo -e "${GREEN}  ✓ Docker gefunden${NC}"

COMPOSE_CMD="docker compose"
if ! $SSH_CMD "docker compose version" 2>/dev/null; then
    if $SSH_CMD "docker-compose --version" 2>/dev/null; then
        COMPOSE_CMD="docker-compose"
    else
        echo -e "${RED}  Docker Compose nicht gefunden!${NC}"
        exit 1
    fi
fi
echo -e "${GREEN}  ✓ Docker Compose gefunden${NC}"

# ==========================================================================
# 4. Passwörter lokal generieren & speichern
# ==========================================================================
echo ""
echo -e "${BOLD}[4/7] Sichere Passwörter generieren...${NC}"

if command -v openssl &>/dev/null; then
    DB_PASS=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)
    ROOT_PASS=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)
    JWT_KEY=$(openssl rand -hex 32)
else
    DB_PASS=$(head -c 64 /dev/urandom | tr -dc 'a-zA-Z0-9' | head -c 32)
    ROOT_PASS=$(head -c 64 /dev/urandom | tr -dc 'a-zA-Z0-9' | head -c 32)
    JWT_KEY=$(head -c 64 /dev/urandom | tr -dc 'a-f0-9' | head -c 64)
fi

cat > "$CREDENTIALS_FILE" <<EOF
==============================================================================
MyRMS - Zugangsdaten
==============================================================================
Erstellt am: $(date '+%Y-%m-%d %H:%M:%S')
URL:         https://${DOMAIN}
NAS:         ${NAS_USER}@${NAS_HOST}:${NAS_PATH}
==============================================================================

Datenbank:
  Host:      db (intern im Docker-Netzwerk)
  Datenbank: myrms
  User:      myrms
  Passwort:  ${DB_PASS}
  Root-PW:   ${ROOT_PASS}
  Port:      3306

JWT Secret Key:
  ${JWT_KEY}

Cloudflare Tunnel:
  Token:     ${TUNNEL_TOKEN}
  Domain:    https://${DOMAIN}

Befehle:
  Status:    ssh -p ${NAS_PORT} ${NAS_USER}@${NAS_HOST} 'cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare ps'
  Logs:      ssh -p ${NAS_PORT} ${NAS_USER}@${NAS_HOST} 'cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare logs -f'
  Neustart:  ssh -p ${NAS_PORT} ${NAS_USER}@${NAS_HOST} 'cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare restart'
  Stoppen:   ssh -p ${NAS_PORT} ${NAS_USER}@${NAS_HOST} 'cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare down'
==============================================================================
EOF

chmod 600 "$CREDENTIALS_FILE"
echo -e "${GREEN}  ✓ Passwörter generiert${NC}"
echo -e "${GREEN}  ✓ Zugangsdaten gespeichert in: ${CREDENTIALS_FILE}${NC}"

# ==========================================================================
# 5. Dateien auf NAS übertragen
# ==========================================================================
echo ""
echo -e "${BOLD}[5/7] Dateien auf NAS übertragen...${NC}"

$SSH_CMD "mkdir -p ${NAS_PATH}/docker/nginx"

for FILE in docker-compose.cloudflare.yml Dockerfile composer.json composer.lock phinx.php migrate.sh php-fpm.conf app.json; do
    echo -e "  Übertrage ${FILE}..."
    $SCP_CMD "$SCRIPT_DIR/$FILE" "${NAS_USER}@${NAS_HOST}:${NAS_PATH}/"
done

for DIR in src db docker scripts vendor; do
    echo -e "  Übertrage ${DIR}/..."
    $SCP_CMD -r "$SCRIPT_DIR/$DIR" "${NAS_USER}@${NAS_HOST}:${NAS_PATH}/"
done

echo -e "${GREEN}  ✓ Alle Dateien übertragen${NC}"

# ==========================================================================
# 6. .env.cloudflare auf NAS erstellen & Stack starten
# ==========================================================================
echo ""
echo -e "${BOLD}[6/7] Konfiguration erstellen & Stack starten...${NC}"

$SSH_CMD bash -s <<EOF
cat > ${NAS_PATH}/.env.cloudflare <<'ENVFILE'
CLOUDFLARE_TUNNEL_TOKEN=${TUNNEL_TOKEN}
APP_DOMAIN=${DOMAIN}
ROOT_URL=https://${DOMAIN}
CORS_ALLOWED_ORIGIN=https://${DOMAIN}
CONFIG_PROJECT_NAME=MyRMS
CONFIG_TIMEZONE=Europe/Berlin
DB_HOSTNAME=db
DB_DATABASE=myrms
DB_USERNAME=myrms
DB_PASSWORD=${DB_PASS}
DB_PORT=3306
MYSQL_ROOT_PASSWORD=${ROOT_PASS}
CONFIG_AUTH_JWTKey=${JWT_KEY}
CONFIG_FILES_ENABLED=Enabled
LOCAL_STORAGE_PATH=/var/www/html/storage
CONFIG_EMAILS_ENABLED=Enabled
CONFIG_EMAILS_PROVIDER=SMTP
CONFIG_EMAILS_FROMEMAIL=noreply@je-soundulight
CONFIG_EMAILS_SMTP_SERVER=smtp.example.com
CONFIG_EMAILS_SMTP_PORT=587
CONFIG_TELEMETRY_NANOID=cloudflare_prod
ENVFILE
chmod 600 ${NAS_PATH}/.env.cloudflare
EOF

echo -e "${GREEN}  ✓ .env.cloudflare auf NAS erstellt${NC}"

# Zugangsdaten auch auf dem NAS speichern
$SSH_CMD bash -s <<EOF
cat > ${NAS_PATH}/myrms-zugangsdaten.txt <<'CREDS'
==============================================================================
MyRMS - Zugangsdaten
==============================================================================
URL:         https://${DOMAIN}
Pfad:        ${NAS_PATH}
==============================================================================

Datenbank:
  Host:      db (intern im Docker-Netzwerk)
  Datenbank: myrms
  User:      myrms
  Passwort:  ${DB_PASS}
  Root-PW:   ${ROOT_PASS}
  Port:      3306

JWT Secret Key:
  ${JWT_KEY}

Cloudflare Tunnel:
  Token:     ${TUNNEL_TOKEN}
  Domain:    https://${DOMAIN}

Befehle (auf dem NAS ausfuehren):
  Status:    cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare ps
  Logs:      cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare logs -f
  Neustart:  cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare restart
  Stoppen:   cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare down
==============================================================================
CREDS
chmod 600 ${NAS_PATH}/myrms-zugangsdaten.txt
EOF
echo -e "${GREEN}  ✓ Zugangsdaten auf NAS gespeichert: ${NAS_PATH}/myrms-zugangsdaten.txt${NC}"

echo ""
echo -e "  Baue Docker Images (das kann einige Minuten dauern)..."
$SSH_CMD "cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare build"
echo -e "${GREEN}  ✓ Images gebaut${NC}"

echo -e "  Starte Stack..."
$SSH_CMD "cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare down 2>/dev/null || true; ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare up -d"
echo -e "${GREEN}  ✓ Stack gestartet${NC}"

# ==========================================================================
# 7. Status prüfen
# ==========================================================================
echo ""
echo -e "${BOLD}[7/7] Status prüfen...${NC}"
sleep 8

echo ""
$SSH_CMD "cd ${NAS_PATH} && ${COMPOSE_CMD} -f docker-compose.cloudflare.yml --env-file .env.cloudflare ps"

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  Deployment abgeschlossen!                              ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${BOLD}URL:${NC}             https://${DOMAIN}"
echo -e "  ${BOLD}Zugangsdaten:${NC}    ${CREDENTIALS_FILE}"
echo ""
echo -e "  ${YELLOW}WICHTIG: Konfiguriere den Public Hostname in Cloudflare Zero Trust:${NC}"
echo -e "    1. https://one.dash.cloudflare.com → Networks → Tunnels"
echo -e "    2. Deinen Tunnel → Configure → Public Hostname → Add"
echo -e "    3. Subdomain: ${CYAN}dash${NC}"
echo -e "    4. Domain:    ${CYAN}je-soundulight${NC} (deine Zone)"
echo -e "    5. Type:      ${CYAN}HTTP${NC}"
echo -e "    6. URL:       ${CYAN}nginx:80${NC}"
echo ""
