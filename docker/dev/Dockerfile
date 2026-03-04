FROM php:8.1-apache

# System-Pakete
RUN apt-get update && apt-get install -y \
    libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    mariadb-client unzip git curl \
    && rm -rf /var/lib/apt/lists/*

# PHP-Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        mysqli pdo pdo_mysql intl zip gd opcache

# Apache: mod_rewrite aktivieren
RUN a2enmod rewrite headers

# Apache: Document Root auf /var/www/html/src setzen
ENV APACHE_DOCUMENT_ROOT=/var/www/html/src
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Apache: AllowOverride All + PassEnv fuer Docker-Umgebungsvariablen
RUN printf '%s\n' \
    '<Directory /var/www/html/src>' \
    '    Options Indexes FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    '' \
    '# Docker env vars an PHP/Apache durchreichen' \
    'PassEnv DB_HOSTNAME' \
    'PassEnv DB_USERNAME' \
    'PassEnv DB_PASSWORD' \
    'PassEnv DB_DATABASE' \
    'PassEnv DB_PORT' \
    'PassEnv DEV_MODE' \
    'PassEnv ROOT_URL' \
    'PassEnv CORS_ALLOWED_ORIGIN' \
    'PassEnv CONFIG_ROOTURL' \
    'PassEnv CONFIG_PROJECT_NAME' \
    'PassEnv CONFIG_TIMEZONE' \
    'PassEnv CONFIG_AUTH_JWTKey' \
    'PassEnv CONFIG_SIGNUP_ENABLED' \
    'PassEnv CONFIG_AWS_S3_KEY' \
    'PassEnv CONFIG_AWS_S3_SECRET' \
    'PassEnv CONFIG_AWS_S3_BUCKET' \
    'PassEnv CONFIG_AWS_S3_BROWSER_ENDPOINT' \
    'PassEnv CONFIG_AWS_S3_SERVER_ENDPOINT' \
    'PassEnv CONFIG_AWS_S3_ENDPOINT_PATHSTYLE' \
    'PassEnv CONFIG_AWS_S3_REGION' \
    'PassEnv CONFIG_AWS_CLOUDFRONT_ENDPOINT' \
    'PassEnv CONFIG_EMAILS_ENABLED' \
    'PassEnv CONFIG_EMAILS_PROVIDER' \
    'PassEnv CONFIG_EMAILS_FROMEMAIL' \
    'PassEnv CONFIG_EMAILS_FROM_EMAIL' \
    'PassEnv CONFIG_EMAILS_SMTP_SERVER' \
    'PassEnv CONFIG_EMAILS_SMTP_PORT' \
    > /etc/apache2/conf-available/adamrms.conf \
    && a2enconf adamrms

# PHP Konfiguration fuer Entwicklung
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
COPY docker/dev/php-dev.ini /usr/local/etc/php/conf.d/99-dev.ini

# Composer installieren
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Entrypoint: Migrationen + Apache starten
COPY docker/dev/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
