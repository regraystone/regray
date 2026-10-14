# syntax=docker/dockerfile:1

FROM php:8.5-cli-trixie AS build

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev ca-certificates curl gnupg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip bcmath \
    && curl -fsSL https://deb.nodesource.com/setup_22.x -o /tmp/nodesource.sh \
    && bash /tmp/nodesource.sh \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -f /tmp/nodesource.sh \
    && rm -rf /var/lib/apt/lists/*

# Pin the Composer 2 source image to its verified minor release.
COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/root/.cache/composer

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/root/.cache/composer \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Host storage may contain private backups, uploads, keys, or test artifacts.
COPY --exclude=storage --exclude=bootstrap/cache . .

# Never carry host configuration caches or development-provider manifests forward.
RUN rm -f bootstrap/cache/*.php \
    && mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs storage/app/public storage/app/private bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction

# This public asset URL is the only build argument. Secrets belong at runtime.
ARG VITE_ASSET_URL
ENV VITE_ASSET_URL=$VITE_ASSET_URL

RUN --mount=type=cache,target=/root/.npm \
    cp .env.example .env \
    && php artisan key:generate --force --no-interaction \
    && php artisan wayfinder:generate --no-interaction \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && rm -f .env

FROM php:8.5-fpm-trixie AS app

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev libpq5 libicu76 libzip5 \
        postgresql-client-17 libfcgi-bin \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip bcmath pcntl \
    && php -r 'exit(extension_loaded("Zend OPcache") ? 0 : 1);' \
    && apt-get purge -y --auto-remove libpq-dev libicu-dev libzip-dev $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHP 8.5 compiles OPcache into PHP; it must not be installed as a shared extension.
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
# Replace, rather than extend, the default pool so it has no user/group directives.
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs storage/app/public storage/app/private bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX,g+rwX storage bootstrap/cache

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/app-entrypoint

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=20s \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q '^pong'

ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["php-fpm"]

FROM caddy:2.11-alpine AS web

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=20s \
    CMD wget -q -O /dev/null http://127.0.0.1:2019/config/ || exit 1
