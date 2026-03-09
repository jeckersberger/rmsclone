#!/usr/bin/env bash
# ==============================================================================
# AdamRMS - Update Script
# ==============================================================================
#
# Zieht die neueste Version aus Git und aktualisiert die laufende
# Docker-Installation, OHNE den Container neu bauen zu muessen.
#
# Voraussetzung: docker-compose.prod.yml mit Volume-Mounts fuer src/, vendor/,
#                db/ und phinx.php (wird automatisch so ausgeliefert).
#
# Usage:
#   ./update.sh                     # Update von default branch (main)
#   ./update.sh --branch develop    # Update von bestimmtem Branch
#   ./update.sh --backup            # Datenbank-Backup vor dem Update
#   ./update.sh --no-migrate        # Migrationen ueberspringen
#
# Exit codes:
#   0 - Erfolgreich
#   1 - Fehler aufgetreten
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# --- Defaults ----------------------------------------------------------------
BRANCH=""
DO_BACKUP=false
DO_MIGRATE=true
COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.prod"

# --- Parse arguments ---------------------------------------------------------
while [[ $# -gt 0 ]]; do
    case "$1" in
        --branch)
            BRANCH="$2"
            shift 2
            ;;
        --backup)
            DO_BACKUP=true
            shift
            ;;
        --no-migrate)
            DO_MIGRATE=false
            shift
            ;;
        --compose-file)
            COMPOSE_FILE="$2"
            shift 2
            ;;
        --help|-h)
            head -25 "$0" | tail -18
            exit 0
            ;;
        *)
            echo "Unbekannte Option: $1" >&2
            exit 1
            ;;
    esac
done

UPDATE_START=$(date +%s)

echo "============================================================"
echo "  AdamRMS Update"
echo "  Gestartet: $(date '+%Y-%m-%d %H:%M:%S')"
echo "============================================================"
echo ""

# --- Pruefen ob Git-Repo vorhanden ------------------------------------------
if [[ ! -d .git ]]; then
    echo "[FEHLER] Kein Git-Repository gefunden in: $SCRIPT_DIR" >&2
    echo "         Das Update-Script muss im Projektverzeichnis liegen." >&2
    exit 1
fi

OLD_REV=$(git rev-parse --short HEAD 2>/dev/null || echo "unbekannt")

# --- 1. Optional: Datenbank-Backup ------------------------------------------
if [[ "$DO_BACKUP" == "true" ]]; then
    echo "[1] Erstelle Datenbank-Backup..."
    if [[ -f scripts/backup-db.sh ]]; then
        bash scripts/backup-db.sh
        echo "    Backup erstellt."
    else
        echo "    [WARNUNG] backup-db.sh nicht gefunden, uebersprungen."
    fi
    echo ""
else
    echo "[1] Datenbank-Backup uebersprungen (--backup zum Aktivieren)"
    echo ""
fi

# --- 2. Git Pull -------------------------------------------------------------
echo "[2] Ziehe neueste Version aus Git..."

# Fetch latest
if [[ -n "$BRANCH" ]]; then
    git fetch origin "$BRANCH"
else
    git fetch origin
    BRANCH=$(git symbolic-ref --short HEAD 2>/dev/null || echo "main")
fi

# Pull with fast-forward only (no merge conflicts)
if git pull --ff-only origin "$BRANCH" 2>&1; then
    NEW_REV=$(git rev-parse --short HEAD)
    if [[ "$OLD_REV" == "$NEW_REV" ]]; then
        echo "    Bereits auf dem neuesten Stand ($NEW_REV)"
    else
        echo "    Aktualisiert: $OLD_REV -> $NEW_REV"
        echo ""
        echo "    Aenderungen:"
        git log --oneline "${OLD_REV}..${NEW_REV}" 2>/dev/null | head -10 | sed 's/^/      /'
    fi
else
    echo "    [FEHLER] Git pull fehlgeschlagen!" >&2
    echo "    Moeglicherweise gibt es lokale Aenderungen." >&2
    echo "    Versuche: git stash && ./update.sh && git stash pop" >&2
    exit 1
fi
echo ""

# --- 3. Composer Dependencies ------------------------------------------------
echo "[3] Aktualisiere Composer-Abhaengigkeiten..."

# Detect the app container name
APP_CONTAINER=$(docker compose -f "$COMPOSE_FILE" ps -q app 2>/dev/null || true)

if [[ -n "$APP_CONTAINER" ]]; then
    # Run composer inside the running container
    docker compose -f "$COMPOSE_FILE" exec -T app bash -c \
        "cd /var/www/html && composer install --no-dev --no-interaction --optimize-autoloader" 2>&1 \
        | tail -5 | sed 's/^/    /'
    echo "    Abhaengigkeiten aktualisiert."
else
    # Container not running - try locally
    if command -v composer &>/dev/null; then
        composer install --no-dev --no-interaction --optimize-autoloader 2>&1 \
            | tail -5 | sed 's/^/    /'
    else
        echo "    [WARNUNG] App-Container nicht gestartet und kein lokales Composer."
        echo "    Starte Container mit: docker compose -f $COMPOSE_FILE up -d"
        echo "    Dann fuehre update.sh erneut aus."
        exit 1
    fi
fi
echo ""

# --- 4. Database Migrations --------------------------------------------------
if [[ "$DO_MIGRATE" == "true" ]]; then
    echo "[4] Fuehre Datenbank-Migrationen aus..."
    if [[ -n "$APP_CONTAINER" ]]; then
        docker compose -f "$COMPOSE_FILE" exec -T app bash -c \
            "cd /var/www/html && php vendor/bin/phinx migrate -e production" 2>&1 \
            | tail -5 | sed 's/^/    /'
        echo "    Migrationen ausgefuehrt."
    else
        echo "    [WARNUNG] App-Container nicht gestartet, Migrationen uebersprungen."
    fi
else
    echo "[4] Migrationen uebersprungen (--no-migrate)"
fi
echo ""

# --- 5. OPcache leeren ------------------------------------------------------
echo "[5] Leere OPcache..."
if [[ -n "$APP_CONTAINER" ]]; then
    docker compose -f "$COMPOSE_FILE" exec -T app bash -c \
        "php -r \"opcache_reset(); echo 'OPcache geleert';\" 2>/dev/null || echo 'OPcache nicht verfuegbar'" 2>&1 \
        | sed 's/^/    /'
else
    echo "    Container nicht gestartet, uebersprungen."
fi
echo ""

# --- 6. Apache graceful restart (ohne Downtime) -----------------------------
echo "[6] Starte Apache neu (graceful)..."
if [[ -n "$APP_CONTAINER" ]]; then
    docker compose -f "$COMPOSE_FILE" exec -T app bash -c \
        "apachectl graceful 2>/dev/null || true"
    echo "    Apache neu gestartet."
else
    echo "    Container nicht gestartet, uebersprungen."
fi
echo ""

# --- Summary -----------------------------------------------------------------
UPDATE_END=$(date +%s)
DURATION=$((UPDATE_END - UPDATE_START))

echo "============================================================"
echo "  Update abgeschlossen"
echo "============================================================"
echo "  Dauer:      ${DURATION}s"
echo "  Vorher:     $OLD_REV"
echo "  Nachher:    ${NEW_REV:-$OLD_REV}"
echo "  Branch:     $BRANCH"
echo "  Status:     ERFOLGREICH"
echo "============================================================"
