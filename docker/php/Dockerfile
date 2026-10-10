# syntax=docker/dockerfile:1

# --- Front : build Vite ---
FROM node:24-alpine AS web-build
WORKDIR /web
COPY web/package.json web/package-lock.json web/.npmrc ./
RUN npm ci --no-audit --no-fund
COPY web/ ./
# URL publique (https://domaine) : Open Graph, hreflang et URL canoniques absolus
ARG SITE_URL=""
ENV VITE_SITE_URL=$SITE_URL
RUN npm run build-only

# --- Base PHP (FrankenPHP = Caddy + PHP) ---
FROM dunglas/frankenphp:1-php8.4-alpine AS base
WORKDIR /app
RUN install-php-extensions pdo_pgsql intl opcache apcu redis zip gmp gd \
 && apk add --no-cache libcap
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

FROM base AS dev
ENV APP_ENV=dev
RUN install-php-extensions xdebug

# --- Production ---
FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0
COPY docker/php/php-prod.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY api/composer.json api/composer.lock api/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress
COPY api/ ./
COPY --from=web-build /web/dist /app/web
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && composer run-script --no-dev post-install-cmd \
 # Photos et avatars : volume persistant monté sur var/storage (propriétaire www-data)
 && mkdir -p var/storage/avatars var/storage/event-photos \
 && chown -R www-data:www-data var \
 # Sans root : autorisation d'écouter les ports 80/443, dossiers Caddy accessibles
 && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
 && chown -R www-data:www-data /data/caddy /config/caddy
USER www-data
