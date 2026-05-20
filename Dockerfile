FROM php:8.2-cli

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by Laravel + maatwebsite/excel
RUN docker-php-ext-install \
    pdo pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy source (vendor excluded by .dockerignore)
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Writable directories
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Migrate + cache + serve at startup (needs DB env vars at runtime)
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan storage:link --force && \
    php artisan migrate --force && \
    php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
