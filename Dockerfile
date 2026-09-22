# ─── Stage 1: PHP dependencies ─────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS php-base

# System packages
RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        gd \
        bcmath \
        intl \
        zip \
        pcntl \
        opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies (production only)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

# Copy application source
COPY . .

# Finalize composer autoloader
RUN composer dump-autoload --optimize --no-dev

# ─── Stage 2: Node / Frontend assets ────────────────────────────────────────
FROM node:20-alpine AS node-build

WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --frozen-lockfile

COPY . .
RUN npm run build

# ─── Stage 3: Nginx + PHP-FPM ───────────────────────────────────────────────
FROM php-base AS production

# Copy built frontend assets
COPY --from=node-build /app/public/build /var/www/html/public/build

# Copy Nginx config
RUN apk add --no-cache nginx supervisor

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
