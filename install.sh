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
echo "   ─── SCHRITT 1: Tunnel erstellen ───"
echo ""
echo "   1. Gehe zu: https://dash.cloudflare.com"
echo "   2. Links im Menü: Networking → Tunnels"
echo "   3. Klicke oben rechts '+ Create Tunnel'"
echo "   4. Gib dem Tunnel einen Namen (z.B. 'myrms') → Save"
echo "   5. Wähle den Tab 'Docker'"
echo "   6. Kopiere den Token aus dem Befehl"
echo "      (der lange Text der mit 'eyJ' anfängt)"
echo ""
echo "   ─── SCHRITT 2: Route einrichten ───"
echo ""
echo "   7. Klicke oben auf den Tab 'Routes'"
echo "   8. Klicke '+ Add route' → 'Published application'"
echo "   9. Trage ein:"
echo "      • Subdomain: z.B. 'dash' oder 'rms'"
echo "      • Domain: deine Domain aus dem Dropdown wählen"
echo "      • Path: leer lassen"
echo "      • Service URL: http://nginx:80"
echo "   10. Klicke 'Add route'"
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
echo ""
echo "   Gib jetzt die Domain ein die du in Schritt 9 konfiguriert"
echo "   hast (Subdomain + Domain zusammen)."
echo ""
read -p "🌐 Deine öffentliche Domain (z.B. rms.meinefirma.de): " PUBLIC_DOMAIN
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
