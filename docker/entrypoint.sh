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
    echo "Waiting for database connection (db:3306)..."
    until timeout 1s bash -c "echo > /dev/tcp/db/3306" 2>/dev/null; do
        echo "Database is not ready yet, retrying in 2 seconds..."
        sleep 2
    done
    echo "Database is up! Running migrations..."
    php artisan migrate --force
fi

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache

# Execute the main command
exec "$@"
