# ==========================================
# Stage 1: Build Frontend Assets with Node
# ==========================================
FROM node:20-alpine AS build-assets
WORKDIR /app

# Copy package descriptors
COPY package*.json ./

# Install npm packages
RUN npm install

# Copy source assets and config files
COPY resources/ ./resources/
COPY vite.config.js ./
# Copy tailwind or postcss configuration if they exist
COPY postcss.config.js* tailwind.config.js* ./

# Compile assets via Vite
RUN npm run build

# ==========================================
# Stage 2: Install PHP Composer Dependencies
# ==========================================
FROM composer:2 AS vendor
WORKDIR /app

# Copy Composer descriptors
COPY composer.json composer.lock ./

# Install dependencies optimized for development/production without running scripts
RUN composer install \
    --no-scripts \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --ignore-platform-reqs

# ==========================================
# Stage 3: Runtime Environment
# ==========================================
FROM php:8.2-fpm-alpine AS runtime
WORKDIR /var/www/html

# Install required system packages and libraries
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    bash \
    icu-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    bcmath \
    zip \
    gd \
    intl \
    opcache \
    pcntl

# Configure PHP settings
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy application files
COPY . .

# Copy vendors and built assets from previous stages
COPY --from=vendor /app/vendor ./vendor
COPY --from=build-assets /app/public/build ./public/build

# Set permissions for Laravel storage and cache directories
RUN mkdir -p storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Copy and setup entrypoint script
COPY .docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
