FROM php:8.3-cli

# System dependencies + Python
RUN apt-get update && apt-get install -y \
    git curl \
    libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip libicu-dev \
    libfreetype6-dev libjpeg62-turbo-dev \
    python3 python3-pip python3-venv \
    && rm -rf /var/lib/apt/lists/*

# GD with freetype+jpeg support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# PHP extensions
RUN docker-php-ext-install \
    pdo pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /app

COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Install Python dependencies for GA engine
RUN pip3 install --no-cache-dir --break-system-packages -r ga-engine/requirements.txt

# Writable directories
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Startup: jalankan GA engine di background, Laravel di foreground
CMD php artisan storage:link --force 2>/dev/null || true && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --force && \
    cd /app/ga-engine && uvicorn main:app --host 0.0.0.0 --port 8001 & \
    cd /app && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
