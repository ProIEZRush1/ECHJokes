# Stage 1: Base with PHP extensions (cached after first build)
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    git curl zip unzip ffmpeg nodejs npm \
    libpng-dev libzip-dev oniguruma-dev sqlite-dev icu-dev \
    libpng icu-libs libzip oniguruma \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql mbstring zip gd pcntl intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Stage 2: Dependencies
FROM base AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY package.json package-lock.json ./
RUN npm ci --prefer-offline

# Stage 3: Build assets
FROM deps AS build
COPY . .
RUN npm run build \
    && composer dump-autoload --optimize \
    && rm -rf node_modules .git tests

# Stage 4a: Production App (Laravel via artisan serve)
FROM base AS production-app
WORKDIR /app
COPY --from=build /app /app
RUN mkdir -p database storage/app/audio storage/app/conversation-audio \
    storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && php artisan migrate --force 2>/dev/null || true \
    && php artisan db:seed --force 2>/dev/null || true \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# Stage 4b: Production WebSocket server
FROM base AS production-ws
WORKDIR /app
COPY --from=build /app /app
RUN mkdir -p database storage/app/audio storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database
EXPOSE 8081
CMD ["php", "artisan", "echjokes:stream-server", "--port=8081"]
