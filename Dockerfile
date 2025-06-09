# Use official PHP 8.2 image with Apache
FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libsqlite3-dev \
    nano \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath

# Install MongoDB extension via PECL
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy Laravel app
COPY . .

# Copy Apache config to point to Laravel public directory
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# Set correct permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Expose default Apache port
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
