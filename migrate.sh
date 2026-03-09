#!/bin/bash

# This file is used by the docker container to build the database schema and seed the database with initial data

cd /var/www/html

# Validate expected environment variables
echo "MyRMS - Checking for Environment Variables"
if [[ ! -v DB_HOSTNAME ]] || [[ ! -v DB_DATABASE ]] || [[ ! -v DB_USERNAME ]] || [[ ! -v DB_PASSWORD ]]; then
    echo "MyRMS - Expected Environment Variables not set"
    exit 1
fi

# Database migration & seed
echo "MyRMS - Starting Migration Script"

php vendor/bin/phinx migrate -e production
php vendor/bin/phinx seed:run -e production

if [[ -v DEV_MODE ]] && [[ "${DEV_MODE}" == 'true' ]]; then
    echo "MyRMS - Running in DEV MODE"
fi

# Start Server
echo "MyRMS - Starting Apache2"
apache2-foreground