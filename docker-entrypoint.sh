#!/bin/bash

# Optional: Wait for MongoDB or MySQL (add logic here if needed)
echo "Waiting for MongoDB..."
until nc -z -v -w30 mongodb 27017
do
  echo "Waiting for MongoDB connection..."
  sleep 1
done


# Exit if any command fails
set -e

# Run Laravel setup tasks
echo "Caching configuration..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Caching views..."
php artisan view:cache

# Ensure necessary permissions
echo "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Start Laravel built-in server (for development only)
echo "Starting Laravel server on 0.0.0.0:8000..."
php artisan serve --host=0.0.0.0 --port=8000
