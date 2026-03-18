#!/bin/bash
# ============================================================================
# MyRMS - Ein-Klick-Installation für Synology NAS + Cloudflare Tunnel
# ============================================================================
#
# Dieses Script macht alles automatisch:
# 1. Generiert sichere Passwörter und Keys
# 2. Fragt den Cloudflare Tunnel Token ab
# 3. Erstellt die .env-Datei
# 4. Startet Docker
# 5. Öffne danach den Browser → Setup-Wizard führt durch den Rest
#
# Aufruf: bash install.sh
# ============================================================================

set -e

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║                                                          ║"
echo "║   MyRMS - Rental Management System                      ║"
echo "║   Installation für Synology NAS                          ║"
echo "║                                                          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# --- Prüfe ob Docker läuft ---
if ! command -v docker &> /dev/null; then
    echo "❌ Docker ist nicht installiert."
    echo "   Bitte installiere den Container Manager im Synology DSM Paketcenter."
    exit 1
fi

if ! docker info &> /dev/null 2>&1; then
    echo "❌ Docker läuft nicht. Bitte starte den Container Manager in DSM."
    exit 1
fi

echo "✅ Docker ist bereit."
echo ""

# --- Generiere sichere Werte automatisch ---
echo "🔐 Generiere sichere Passwörter und Keys..."
DB_PASSWORD=$(openssl rand -hex 16)
MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)
JWT_KEY=$(openssl rand -hex 32)
echo "   ✅ Datenbank-Passwort generiert"
echo "   ✅ Root-Passwort generiert"
echo "   ✅ JWT-Key generiert"
echo ""

# --- Cloudflare Tunnel Token abfragen ---
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📡 Cloudflare Tunnel einrichten"
echo ""
echo "   Der Cloudflare Tunnel verbindet dein NAS sicher mit dem"
echo "   Internet – ohne Ports zu öffnen."
echo ""
echo "   So bekommst du den Tunnel-Token:"
echo ""
echo "   1. Gehe zu: https://one.dash.cloudflare.com"
echo "   2. Wähle dein Konto → Networks → Tunnels"
echo "   3. Klicke 'Create a tunnel'"
echo "   4. Wähle 'Cloudflared' als Typ"
echo "   5. Gib dem Tunnel einen Namen (z.B. 'myrms')"
echo "   6. Cloudflare zeigt dir einen Token an"
echo "   7. Kopiere den Token (fängt an mit 'eyJ...')"
echo ""
echo "   WICHTIG: Im nächsten Schritt in Cloudflare musst du"
echo "   unter 'Public Hostname' folgendes eintragen:"
echo "   • Subdomain: z.B. 'dash' oder 'rms'"
echo "   • Domain: deine Domain (z.B. 'je-soundulight.de')"
echo "   • Service Type: HTTP"
echo "   • URL: nginx:80"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
read -p "📋 Tunnel-Token einfügen (oder Enter für später): " TUNNEL_TOKEN
echo ""

if [ -z "$TUNNEL_TOKEN" ]; then
    echo "⚠️  Kein Token eingegeben. Du kannst den Tunnel später"
    echo "   in der .env Datei nachtragen."
    TUNNEL_TOKEN="DEIN_TUNNEL_TOKEN_HIER"
fi

# --- Domain abfragen ---
read -p "🌐 Deine öffentliche Domain (z.B. dash.meinefirma.de): " PUBLIC_DOMAIN
echo ""

if [ -z "$PUBLIC_DOMAIN" ]; then
    PUBLIC_DOMAIN="localhost"
    ROOT_URL="http://localhost:8080"
    echo "   → Kein Domain eingegeben, nutze http://localhost:8080"
else
    ROOT_URL="https://${PUBLIC_DOMAIN}"
    echo "   → URL wird: https://${PUBLIC_DOMAIN}"
fi
echo ""

# --- .env Datei erstellen ---
echo "📝 Erstelle Konfiguration..."

cat > .env << EOF
# ============================================================================
# MyRMS - Automatisch generierte Konfiguration
# Erstellt: $(date '+%Y-%m-%d %H:%M:%S')
# ============================================================================

# Datenbank (automatisch generiert – NICHT ÄNDERN)
DB_PASSWORD=${DB_PASSWORD}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}

# Sicherheit (automatisch generiert)
JWT_KEY=${JWT_KEY}

# Öffentliche URL
ROOT_URL=${ROOT_URL}

# Projektname
PROJECT_NAME=MyRMS

# Zeitzone
TIMEZONE=Europe/Berlin

# E-Mail (kann später im Setup-Wizard konfiguriert werden)
EMAILS_ENABLED=Disabled
EMAILS_PROVIDER=SMTP
EMAILS_FROM=noreply@example.com
SMTP_SERVER=
SMTP_PORT=587

# Cloudflare Tunnel
TUNNEL_TOKEN=${TUNNEL_TOKEN}
EOF

echo "   ✅ .env erstellt"
echo ""

# --- .env zu .gitignore hinzufügen (falls nicht schon drin) ---
if ! grep -q "^\.env$" .gitignore 2>/dev/null; then
    echo ".env" >> .gitignore
    echo "   ✅ .env zu .gitignore hinzugefügt"
fi

# --- Docker starten ---
echo "🚀 Starte MyRMS..."
echo "   (Das kann beim ersten Mal 3-5 Minuten dauern)"
echo ""

docker compose -f docker-compose.synology-cloudflare.yml up -d --build 2>&1 | while read line; do
    echo "   $line"
done

echo ""
echo "⏳ Warte auf Datenbankstart..."
sleep 10

# Warte bis DB gesund ist
for i in $(seq 1 30); do
    if docker compose -f docker-compose.synology-cloudflare.yml exec -T db mysqladmin ping -h localhost --silent 2>/dev/null; then
        echo "   ✅ Datenbank ist bereit"
        break
    fi
    sleep 2
done

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║                                                          ║"
echo "║   ✅ MyRMS wurde erfolgreich installiert!                ║"
echo "║                                                          ║"
echo "║   Öffne jetzt deinen Browser:                            ║"
echo "║                                                          ║"
if [ "$PUBLIC_DOMAIN" = "localhost" ]; then
echo "║   👉 http://DEINE-NAS-IP:8080                           ║"
else
echo "║   👉 https://${PUBLIC_DOMAIN}                     ║"
fi
echo "║                                                          ║"
echo "║   Der Setup-Wizard führt dich durch die                  ║"
echo "║   restliche Einrichtung im Browser.                      ║"
echo "║                                                          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Nützliche Befehle:"
echo "  Logs anzeigen:  docker compose -f docker-compose.synology-cloudflare.yml logs -f"
echo "  Stoppen:        docker compose -f docker-compose.synology-cloudflare.yml down"
echo "  Neustarten:     docker compose -f docker-compose.synology-cloudflare.yml restart"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
