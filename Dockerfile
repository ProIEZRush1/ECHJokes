FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    git curl zip unzip ffmpeg nodejs npm \
    libpng-dev libzip-dev oniguruma-dev sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql mbstring zip gd pcntl

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN npm run build
RUN composer dump-autoload --optimize

# Create SQLite database
RUN mkdir -p database && touch database/database.sqlite
RUN php artisan migrate --force || true
RUN php artisan db:seed --force || true

RUN chown -R www-data:www-data storage bootstrap/cache database
RUN chmod -R 775 storage bootstrap/cache database

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
