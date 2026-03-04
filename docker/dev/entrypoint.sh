#!/bin/bash
set -e

echo "=== AdamRMS Dev Environment ==="

# Storage-Verzeichnis erstellen
STORAGE_DIR="${LOCAL_STORAGE_PATH:-/var/www/html/storage}"
mkdir -p "$STORAGE_DIR/uploads"
chown -R www-data:www-data "$STORAGE_DIR"
echo "[0/3] Storage directory ready: $STORAGE_DIR"

# Composer install (falls vendor/ nicht existiert oder veraltet)
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "[1/3] Installing Composer dependencies..."
    cd /var/www/html && composer install --no-interaction --prefer-dist
else
    echo "[1/3] Composer dependencies already installed."
fi

# Warten bis MySQL bereit ist
echo "[2/3] Waiting for MySQL..."
MAX_TRIES=30
COUNT=0
until mysqladmin ping -h "$DB_HOSTNAME" -u "$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null; do
    COUNT=$((COUNT + 1))
    if [ $COUNT -ge $MAX_TRIES ]; then
        echo "ERROR: MySQL not ready after ${MAX_TRIES} attempts. Starting anyway..."
        break
    fi
    sleep 2
done
echo "MySQL is ready."

# Migrationen ausfuehren
echo "[3/3] Running database migrations..."
cd /var/www/html
php vendor/bin/phinx migrate -e development 2>&1 || echo "WARNING: Migration had errors (may be OK on first run)"
php vendor/bin/phinx seed:run 2>&1 || echo "WARNING: Seed had errors (may be OK if already seeded)"

echo ""
echo "=== AdamRMS ready! ==="
echo "  App:        http://localhost:8080"
echo "  phpMyAdmin: http://localhost:8082"
echo "  Mailpit:    http://localhost:8083"
echo ""

# Apache starten
exec "$@"
