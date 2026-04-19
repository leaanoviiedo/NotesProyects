#!/bin/bash

# Ensure we're in the right directory
cd /var/www/html

# Create .env if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
    
    # Generate app key since it's a new .env
    echo "Generating app key..."
    php artisan key:generate --force
fi

# Set correct permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Run migrations (if you want this automatically)
# Be careful with this in production if you have multiple containers
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Waiting for database connection..."
    # We can use php artisan db:monitor or just wait
    sleep 10
    echo "Running migrations..."
    php artisan migrate --force
fi

# Clear and cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Execute the main command
exec "$@"
