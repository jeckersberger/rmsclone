#!/bin/bash
#
# Setup Wizard Validation Script
# Ensures the setup wizard can run properly before application starts
# Run this AFTER database migrations (migrate.sh) and BEFORE starting Apache
#

set -e

echo "=================================================="
echo "MyRMS Setup Wizard - Validation"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Checks
ERROR_COUNT=0

echo ""
echo "1. Checking environment variables..."
if [ -z "$DB_HOSTNAME" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ] || [ -z "$DB_DATABASE" ]; then
    echo -e "${RED}✗ Database environment variables not set${NC}"
    ERROR_COUNT=$((ERROR_COUNT + 1))
else
    echo -e "${GREEN}✓ Database environment variables OK${NC}"
fi

echo ""
echo "2. Checking setup wizard files..."
if [ ! -f "/var/www/html/src/setup/index.php" ]; then
    echo -e "${RED}✗ Setup wizard index.php not found${NC}"
    ERROR_COUNT=$((ERROR_COUNT + 1))
else
    echo -e "${GREEN}✓ Setup wizard index.php found${NC}"
fi

if [ ! -f "/var/www/html/src/setup/process.php" ]; then
    echo -e "${RED}✗ Setup wizard process.php not found${NC}"
    ERROR_COUNT=$((ERROR_COUNT + 1))
else
    echo -e "${GREEN}✓ Setup wizard process.php found${NC}"
fi

if [ ! -f "/var/www/html/src/setup/setup_wizard.twig" ]; then
    echo -e "${RED}✗ Setup wizard template not found${NC}"
    ERROR_COUNT=$((ERROR_COUNT + 1))
else
    echo -e "${GREEN}✓ Setup wizard template found${NC}"
fi

echo ""
echo "3. Checking webroot permissions..."
if [ ! -w "/var/www/html" ]; then
    echo -e "${RED}✗ /var/www/html is not writable${NC}"
    ERROR_COUNT=$((ERROR_COUNT + 1))
else
    echo -e "${GREEN}✓ /var/www/html is writable${NC}"
fi

echo ""
echo "4. Checking database connection (waiting up to 30 seconds)..."
MAX_RETRIES=30
RETRY=0
DB_READY=0

while [ $RETRY -lt $MAX_RETRIES ]; do
    if mysqladmin ping -h "$DB_HOSTNAME" -u "$DB_USERNAME" -p"$DB_PASSWORD" -q 2>/dev/null; then
        echo -e "${GREEN}✓ Database connection OK${NC}"
        DB_READY=1
        break
    fi
    RETRY=$((RETRY + 1))
    echo -n "."
    sleep 1
done

if [ $DB_READY -eq 0 ]; then
    echo ""
    echo -e "${RED}✗ Database not responding after 30 seconds${NC}"
    ERROR_COUNT=$((ERROR_COUNT + 1))
fi

echo ""
echo "5. Checking if migrations have run..."
# Count tables in database
TABLE_COUNT=$(mysql -h "$DB_HOSTNAME" -u "$DB_USERNAME" -p"$DB_PASSWORD" -D "$DB_DATABASE" -sN -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -lt 80 ]; then
    echo -e "${YELLOW}⚠ Only $TABLE_COUNT tables found (expected ~87)${NC}"
    echo -e "${YELLOW}  Did migrations run? Check migrate.sh output${NC}"
else
    echo -e "${GREEN}✓ Found $TABLE_COUNT tables (migrations appear complete)${NC}"
fi

echo ""
echo "6. Checking setup marker..."
if [ -f "/var/www/html/.setup_complete" ]; then
    echo -e "${YELLOW}⚠ Setup marker already exists${NC}"
    echo "   Setup has been run before. Wizard will skip and redirect to login."
else
    echo -e "${GREEN}✓ No setup marker (first run - wizard will show)${NC}"
fi

echo ""
echo "=================================================="

if [ $ERROR_COUNT -eq 0 ]; then
    echo -e "${GREEN}All validation checks passed!${NC}"
    echo ""
    echo "Setup wizard will appear at:"
    echo "  http://localhost/setup/"
    echo ""
    echo "User can now:"
    echo "  1. Open browser to http://localhost"
    echo "  2. Complete 6-step wizard"
    echo "  3. Create admin account and company"
    echo "  4. Login and use MyRMS"
    echo ""
    exit 0
else
    echo -e "${RED}$ERROR_COUNT validation check(s) failed${NC}"
    echo ""
    echo "Please fix the above issues and try again."
    exit 1
fi
