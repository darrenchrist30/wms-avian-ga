FROM php:8.3-cli

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl \
    libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip libicu-dev \
    libfreetype6-dev libjpeg62-turbo-dev \
    && rm -rf /var/lib/apt/lists/*

# GD with freetype+jpeg support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# PHP extensions required by Laravel + maatwebsite/excel + dompdf
RUN docker-php-ext-install \
    pdo pdo_mysql mbstring exif pcntl bcmath \
    gd zip intl \
    xml dom simplexml xmlreader xmlwriter \
    fileinfo

# Composer (with memory unlimited untuk build)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /app

# Copy source (vendor excluded by .dockerignore)
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

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
