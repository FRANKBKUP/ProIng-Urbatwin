# syntax=docker/dockerfile:1
FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

FROM composer:2 AS composer

FROM php:8.4-apache-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev unzip \
    && docker-php-ext-install -j "$(nproc)" intl pdo_pgsql \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite setenvif \
    && sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i -e 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' \
        -e 's|DocumentRoot /var/www/html$|DocumentRoot /var/www/html/public|' \
        /etc/apache2/sites-available/000-default.conf

RUN <<'CONFIG'
cat > /etc/apache2/conf-available/urbantwin.conf <<'APACHE'
ServerName localhost
SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on
<IfModule mpm_prefork_module>
    StartServers 2
    MinSpareServers 1
    MaxSpareServers 2
    MaxRequestWorkers 4
</IfModule>
<Directory /var/www/html/public>
    AllowOverride All
    Options -Indexes
    Require all granted
</Directory>
APACHE
a2enconf urbantwin
cat > /usr/local/etc/php/conf.d/urbantwin.ini <<'PHP'
expose_php=Off
display_errors=Off
upload_max_filesize=5M
post_max_size=8M
PHP
CONFIG

WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN mkdir -p bootstrap/cache storage/app/private storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist \
        --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data bootstrap/cache storage

EXPOSE 10000
CMD ["sh", "-ec", "if [ -z \"${APP_KEY:-}\" ]; then export APP_KEY=\"base64:${LARAVEL_KEY_BASE64:?Falta configurar LARAVEL_KEY_BASE64}\"; fi; export APP_URL=\"${RENDER_EXTERNAL_URL:-${APP_URL:-http://localhost}}\"; php artisan migrate --force --no-interaction; exec apache2-foreground"]
