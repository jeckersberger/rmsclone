#!/usr/bin/env bash
# ==============================================================================
# MyRMS - Cloudflare Tunnel Automatisches Setup
# ==============================================================================
# Dieses Script:
#   1. Erstellt .env.cloudflare mit sicheren, zufälligen Passwörtern
#   2. Baut die Docker-Images
#   3. Startet den gesamten Stack
#
# Voraussetzung:
#   - Docker + Docker Compose installiert
#   - Cloudflare Tunnel Token bereit (aus Zero Trust Dashboard)
#
# Verwendung:
#   bash scripts/setup-cloudflare.sh
#
# Ergebnis:
#   App erreichbar unter: https://dash.je-soundulight
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env.cloudflare"
COMPOSE_FILE="$PROJECT_DIR/docker-compose.cloudflare.yml"

# Farben
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║       MyRMS - Cloudflare Tunnel Setup                   ║"
echo "║       Domain: dash.je-soundulight                       ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# --------------------------------------------------------------------------
# Hilfsfunktionen
# --------------------------------------------------------------------------
generate_password() {
    openssl rand -base64 32 | tr -d '/+=' | head -c 32
}

generate_jwt_key() {
    openssl rand -hex 32
}

# --------------------------------------------------------------------------
# Prüfe Voraussetzungen
# --------------------------------------------------------------------------
echo -e "${YELLOW}[1/5] Prüfe Voraussetzungen...${NC}"

if ! command -v docker &>/dev/null; then
    echo -e "${RED}Fehler: Docker ist nicht installiert!${NC}"
    echo "Installiere Docker: https://docs.docker.com/get-docker/"
    exit 1
fi

if ! docker compose version &>/dev/null; then
    echo -e "${RED}Fehler: Docker Compose V2 ist nicht installiert!${NC}"
    exit 1
fi

echo -e "${GREEN}  ✓ Docker gefunden: $(docker --version)${NC}"
echo -e "${GREEN}  ✓ Docker Compose gefunden: $(docker compose version)${NC}"

# --------------------------------------------------------------------------
# Cloudflare Tunnel Token abfragen
# --------------------------------------------------------------------------
echo ""
echo -e "${YELLOW}[2/5] Cloudflare Tunnel konfigurieren...${NC}"

TUNNEL_TOKEN=""

if [ -f "$ENV_FILE" ]; then
    # Bestehenden Token auslesen
    EXISTING_TOKEN=$(grep -oP 'CLOUDFLARE_TUNNEL_TOKEN=\K.*' "$ENV_FILE" 2>/dev/null || true)
    if [ -n "$EXISTING_TOKEN" ] && [ "$EXISTING_TOKEN" != "DEIN_TUNNEL_TOKEN_HIER" ]; then
        echo -e "  Bestehende .env.cloudflare gefunden."
        read -rp "  Bestehende Konfiguration überschreiben? (j/N): " OVERWRITE
        if [[ ! "$OVERWRITE" =~ ^[jJyY]$ ]]; then
            echo -e "${GREEN}  ✓ Bestehende Konfiguration wird beibehalten.${NC}"
            TUNNEL_TOKEN="$EXISTING_TOKEN"
        fi
    fi
fi

if [ -z "$TUNNEL_TOKEN" ]; then
    echo ""
    echo -e "${CYAN}  So erstellst du den Cloudflare Tunnel Token:${NC}"
    echo "  1. Öffne: https://one.dash.cloudflare.com"
    echo "  2. Gehe zu: Zero Trust → Networks → Tunnels"
    echo "  3. Klicke 'Create a tunnel'"
    echo "  4. Wähle 'Cloudflared' als Connector"
    echo "  5. Name: z.B. 'myrms-dash'"
    echo "  6. Kopiere den Token"
    echo "  7. Unter 'Public Hostname' konfiguriere:"
    echo "     - Subdomain: dash"
    echo "     - Domain: je-soundulight (deine Zone auswählen)"
    echo "     - Type: HTTP"
    echo "     - URL: nginx:80"
    echo ""
    read -rp "  Tunnel Token eingeben: " TUNNEL_TOKEN

    if [ -z "$TUNNEL_TOKEN" ]; then
        echo -e "${RED}  Fehler: Kein Token eingegeben!${NC}"
        echo -e "  Du kannst den Token später in ${ENV_FILE} nachtragen."
        TUNNEL_TOKEN="DEIN_TUNNEL_TOKEN_HIER"
    fi
fi

# --------------------------------------------------------------------------
# .env.cloudflare erstellen
# --------------------------------------------------------------------------
echo ""
echo -e "${YELLOW}[3/5] Konfiguration erstellen...${NC}"

DB_PASS=$(generate_password)
ROOT_PASS=$(generate_password)
JWT_KEY=$(generate_jwt_key)

cat > "$ENV_FILE" <<EOF
# ==============================================================================
# MyRMS - Cloudflare Tunnel Deployment
# ==============================================================================
# Automatisch generiert am: $(date '+%Y-%m-%d %H:%M:%S')
# Domain: https://dash.je-soundulight
# ==============================================================================

# --- Cloudflare Tunnel -------------------------------------------------------
CLOUDFLARE_TUNNEL_TOKEN=${TUNNEL_TOKEN}

# --- Domain ------------------------------------------------------------------
APP_DOMAIN=dash.je-soundulight
ROOT_URL=https://dash.je-soundulight
CORS_ALLOWED_ORIGIN=https://dash.je-soundulight

# --- Projekt -----------------------------------------------------------------
CONFIG_PROJECT_NAME=MyRMS
CONFIG_TIMEZONE=Europe/Berlin

# --- Datenbank ---------------------------------------------------------------
DB_HOSTNAME=db
DB_DATABASE=myrms
DB_USERNAME=myrms
DB_PASSWORD=${DB_PASS}
DB_PORT=3306
MYSQL_ROOT_PASSWORD=${ROOT_PASS}

# --- Authentifizierung -------------------------------------------------------
CONFIG_AUTH_JWTKey=${JWT_KEY}

# --- Datei-Speicher ----------------------------------------------------------
CONFIG_FILES_ENABLED=Enabled
LOCAL_STORAGE_PATH=/var/www/html/storage

# --- E-Mail / SMTP -----------------------------------------------------------
CONFIG_EMAILS_ENABLED=Enabled
CONFIG_EMAILS_PROVIDER=SMTP
CONFIG_EMAILS_FROMEMAIL=noreply@je-soundulight
CONFIG_EMAILS_SMTP_SERVER=smtp.example.com
CONFIG_EMAILS_SMTP_PORT=587

# --- Telemetrie --------------------------------------------------------------
CONFIG_TELEMETRY_NANOID=cloudflare_prod
EOF

echo -e "${GREEN}  ✓ .env.cloudflare erstellt${NC}"
echo -e "  DB-Passwort:   ${DB_PASS:0:8}..."
echo -e "  Root-Passwort: ${ROOT_PASS:0:8}..."
echo -e "  JWT-Key:       ${JWT_KEY:0:16}..."

# --------------------------------------------------------------------------
# Docker Images bauen
# --------------------------------------------------------------------------
echo ""
echo -e "${YELLOW}[4/5] Docker Images bauen...${NC}"

cd "$PROJECT_DIR"
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" build

echo -e "${GREEN}  ✓ Images gebaut${NC}"

# --------------------------------------------------------------------------
# Stack starten
# --------------------------------------------------------------------------
echo ""
echo -e "${YELLOW}[5/5] Stack starten...${NC}"

docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  Setup abgeschlossen!                                   ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${CYAN}URL:${NC}     https://dash.je-soundulight"
echo -e "  ${CYAN}Stack:${NC}   docker compose -f docker-compose.cloudflare.yml --env-file .env.cloudflare ps"
echo -e "  ${CYAN}Logs:${NC}    docker compose -f docker-compose.cloudflare.yml --env-file .env.cloudflare logs -f"
echo -e "  ${CYAN}Stop:${NC}    docker compose -f docker-compose.cloudflare.yml --env-file .env.cloudflare down"
echo ""

if [ "$TUNNEL_TOKEN" = "DEIN_TUNNEL_TOKEN_HIER" ]; then
    echo -e "${YELLOW}  ⚠  WICHTIG: Trage deinen Cloudflare Tunnel Token in .env.cloudflare ein!${NC}"
    echo -e "     Dann: docker compose -f docker-compose.cloudflare.yml --env-file .env.cloudflare up -d cloudflared"
    echo ""
fi

# Warte kurz und zeige Status
sleep 3
echo -e "${CYAN}Container Status:${NC}"
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps
