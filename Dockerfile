# Dockerfile
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies in separate steps to avoid conflicts
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
RUN apt-get update

# Install basic dependencies first
RUN apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    poppler-utils

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js (for Vite/asset compilation if needed later)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Playwright dependencies removed for simplified build

# Copy existing application directory contents
COPY . /var/www/html

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node.js dependencies (Playwright browsers removed)
RUN npm install

# Generate Laravel key (will be overridden by env)
RUN php artisan key:generate --no-interaction

# Expose port 80
EXPOSE 80

# Start supervisor (manages nginx and php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]