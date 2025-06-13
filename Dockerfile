# Use official PHP 8.2 with Apache
FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git curl unzip zip nano \
    libpng-dev libjpeg-dev libonig-dev \
    libxml2-dev libzip-dev libcurl4-openssl-dev \
    libssl-dev libsqlite3-dev libpq-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath

# Install MongoDB extension via PECL
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy Laravel app files
COPY . .

# Copy Apache config (points to /public)
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions for storage and cache
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Cache config, routes, views
RUN php artisan config:cache \
 && php artisan route:cache \
 && php artisan view:cache

# Expose Apache default port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
