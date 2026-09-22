#!/bin/sh
set -e

echo "=== Spendenquittung Startup ==="

# Run migrations
echo "Running migrations..."
php artisan migrate --force --no-interaction

# Create storage symlink
php artisan storage:link --force 2>/dev/null || true

# Create storage directories if they don't exist
mkdir -p storage/app/public/pdfs
mkdir -p storage/app/public/unterschriften
mkdir -p storage/app/public/logos

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Cache config/routes/views for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== Starting services ==="
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
