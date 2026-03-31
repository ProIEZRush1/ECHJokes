# Stage 1: Base with PHP extensions (cached)
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    git curl zip unzip ffmpeg nodejs npm nginx supervisor \
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

# Stage 3: Build
FROM deps AS build
COPY . .
RUN npm run build \
    && composer dump-autoload --optimize \
    && rm -rf node_modules .git tests

# Stage 4: Production
FROM base AS production
WORKDIR /app
COPY --from=build /app /app
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

RUN mkdir -p database storage/app/audio storage/app/conversation-audio \
    storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache /run/nginx \
    && touch database/database.sqlite \
    && php artisan migrate --force 2>/dev/null || true \
    && php artisan db:seed --force 2>/dev/null || true \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
