# syntax=docker/dockerfile:1
FROM dunglas/frankenphp:1-php8.4-alpine AS base
WORKDIR /app
RUN install-php-extensions pdo_pgsql intl opcache apcu redis zip gmp
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

FROM base AS dev
ENV APP_ENV=dev
RUN install-php-extensions xdebug

FROM base AS prod
ENV APP_ENV=prod
COPY docker/php/php-prod.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY api/composer.json api/composer.lock api/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress
COPY api/ ./
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && composer run-script --no-dev post-install-cmd \
 && chown -R www-data:www-data var
USER www-data
