#!/bin/bash

# Wait for dependencies (optional: DB, MongoDB, etc.)

# Run Laravel setup tasks
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Laravel's built-in server
php artisan serve --host=0.0.0.0 --port=8000
