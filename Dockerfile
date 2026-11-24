# syntax=docker/dockerfile:1
FROM node:24-bookworm-slim AS node
FROM composer:2 AS composer
FROM dunglas/frankenphp:1-php8.5-bookworm AS php-runtime

WORKDIR /app
RUN install-php-extensions gd iconv pcntl pdo_pgsql pdo_sqlite
RUN printf 'upload_max_filesize=5M\npost_max_size=6M\n' > "$PHP_INI_DIR/conf.d/profile-uploads.ini"

# Desenvolvimento: o código vem do bind mount; vendor e node_modules ficam em volumes do usuário do host.
FROM php-runtime AS dev
ARG APP_UID=1000
ARG APP_GID=1000
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /app/vendor /app/node_modules \
    && chown -R "$APP_UID:$APP_GID" /app /config/caddy /data/caddy
ENV HOME=/tmp

FROM php-runtime AS build
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock package.json package-lock.json .npmrc ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader \
    && npm ci --no-audit --no-fund

COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && npm run build \
    && rm -rf node_modules

FROM php-runtime AS app
COPY --from=build /app /app
ENTRYPOINT ["/bin/sh", "/app/docker/start.sh"]
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=2", "--caddyfile=/app/docker/Caddyfile"]
