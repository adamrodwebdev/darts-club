# syntax=docker/dockerfile:1

# --- Front : build Vite ---
FROM node:26-alpine AS web-build
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
# Correctifs Alpine du jour (OpenSSL, musl, curl…) : l'image de base n'est pas republiée à chaque correctif,
# et la reconstruction du lundi se fait sans cache (deploy.yml), donc ffmpeg et le reste sont aussi à jour
RUN apk upgrade --no-cache \
 && install-php-extensions pdo_pgsql intl opcache apcu redis zip gmp gd \
 && apk add --no-cache libcap ffmpeg \
 # Galerie : formats les plus légers vérifiés à la construction (l'image ne part pas sans eux) :
 # AVIF par GD (photos), H.264 et WebP par ffmpeg (vidéos, aperçus), un encodeur AV1
 && php -r 'exit(function_exists("imageavif") ? 0 : 1);' \
 && ffmpeg -hide_banner -encoders | grep -q ' libx264 ' \
 && ffmpeg -hide_banner -encoders | grep -q ' libwebp ' \
 && ffmpeg -hide_banner -encoders | grep -qE ' (libsvtav1|libaom-av1) '
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
# Fichiers de déploiement de cette version : le serveur les tire de l'image (deploy/ssh-gate.sh), la clé
# SSH de GitHub Actions ne pouvant rien copier
COPY compose.yaml compose.prod.yaml /app/ops/
COPY deploy /app/ops/deploy
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && composer run-script --no-dev post-install-cmd \
 # Photos, avatars et vidéos : volume persistant monté sur var/storage (propriétaire www-data)
 && mkdir -p var/storage/avatars var/storage/event-photos var/storage/event-videos var/storage/video-uploads var/storage/video-work \
 && chown -R www-data:www-data var \
 # Sans root : autorisation d'écouter les ports 80/443, dossiers Caddy accessibles
 && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
 && chown -R www-data:www-data /data/caddy /config/caddy
USER www-data
