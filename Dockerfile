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

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js (for Vite/asset compilation if needed later)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install Playwright system dependencies for Chromium
RUN apt-get install -y \
    libnss3 \
    libnspr4 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libdbus-1-3 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libpango-1.0-0 \
    libcairo2 \
    libasound2 \
    libatspi2.0-0

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

# Install Node.js dependencies and Playwright Chromium
RUN npm install

# Install Playwright Chromium browser (required for job scraping)
# Create cache directory and set environment before installation
RUN mkdir -p /var/www/html/.cache
ENV PLAYWRIGHT_BROWSERS_PATH=/var/www/html/.cache

# Install browsers with the environment variable explicitly set
RUN PLAYWRIGHT_BROWSERS_PATH=/var/www/html/.cache npx playwright install chromium --with-deps

# Ensure www-data owns the cache directory and verify installation
RUN chown -R www-data:www-data /var/www/html/.cache && \
    ls -la /var/www/html/.cache/ && \
    echo "Playwright browsers installed to /var/www/html/.cache/"

# Generate Laravel key (will be overridden by env)
RUN php artisan key:generate --no-interaction

# Expose port 80
EXPOSE 80

# Start supervisor (manages nginx and php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]