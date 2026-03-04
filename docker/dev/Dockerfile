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
RUN echo '<Directory /var/www/html/src>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n\
\n\
# Docker env vars an PHP/Apache durchreichen\n\
PassEnv DB_HOSTNAME\n\
PassEnv DB_USERNAME\n\
PassEnv DB_PASSWORD\n\
PassEnv DB_DATABASE\n\
PassEnv DB_PORT\n\
PassEnv DEV_MODE\n\
PassEnv CONFIG_ROOTURL\n\
PassEnv CONFIG_AUTH_JWTKey\n\
PassEnv CORS_ALLOWED_ORIGIN\n\
PassEnv S3_ENDPOINT\n\
PassEnv S3_BUCKET\n\
PassEnv S3_KEY\n\
PassEnv S3_SECRET\n\
PassEnv S3_REGION\n\
PassEnv MAIL_HOST\n\
PassEnv MAIL_PORT' > /etc/apache2/conf-available/adamrms.conf \
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
