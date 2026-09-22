# ─── Stage 1: Frontend-Assets (Vite) ─────────────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ─── Stage 2: Composer dependencies ──────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ─── Stage 3: Runtime ─────────────────────────────────────────────────────────
FROM serversideup/php:8.3-fpm-nginx-alpine

ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    PHP_OPCACHE_ENABLE=1

USER root
RUN install-php-extensions intl gd zip bcmath pdo_pgsql pcntl
USER www-data

WORKDIR /var/www/html

COPY --chown=www-data:www-data --from=vendor /app /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build
