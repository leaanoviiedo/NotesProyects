# =============================================================================
# Stage 1: Build frontend assets
# =============================================================================
FROM node:20-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public

RUN npm run build

# =============================================================================
# Stage 2: Production image
# =============================================================================
FROM php:8.4-fpm-alpine AS app

LABEL maintainer="NotesProjects"
LABEL description="Laravel app with Reverb WebSocket, Queue Worker and Scheduler"

# Install system dependencies (no -dev headers: los maneja install-php-extensions)
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    git \
    zip \
    unzip \
    netcat-openbsd \
    mysql-client \
    && rm -rf /var/cache/apk/*

# install-php-extensions resuelve automáticamente headers y rutas en cualquier Alpine/ARM
RUN curl -sSLf \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql \
        pdo_sqlite \
        gd \
        zip \
        bcmath \
        opcache \
        pcntl \
        sockets \
        mbstring \
        redis

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer files first (Docker layer caching)
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader

# Copy full application
COPY . .

# Copy built frontend assets from node-builder stage
COPY --from=node-builder /app/public/build ./public/build

# Finalize composer autoloader
RUN composer dump-autoload --optimize --no-dev

# Set correct permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache \
    && mkdir -p /var/www/storage/logs \
    && chmod -R 775 /var/www/storage/logs

# Copy service configurations
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

# Expose HTTP and Reverb WebSocket ports
EXPOSE 80 8080

ENTRYPOINT ["/entrypoint.sh"]
