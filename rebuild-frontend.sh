#!/bin/bash

# NOTE: If you've pulled new database migrations, run them first:
# ./vendor/bin/sail artisan migrate
# Always use migrations for database changes, never alter tables directly!

echo "🔨 Building frontend assets..."
docker exec defrag-racing-project-laravel.test-1 bash -c "cd /var/www/html && npm run build"

echo ""
echo "🔄 Reloading Octane..."
docker exec defrag-racing-project-laravel.test-1 php artisan octane:reload

echo ""
echo "🧹 Clearing caches..."
docker exec defrag-racing-project-laravel.test-1 php artisan config:clear
docker exec defrag-racing-project-laravel.test-1 php artisan view:clear

echo ""
echo "✅ Frontend rebuild complete! Assets are now available."
