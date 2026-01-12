#!/bin/sh
set -e

# Fix permissions (important for Laravel/Filament)
chmod -R 775 storage bootstrap/cache || true

# Clear cached config/routes/views
php artisan optimize:clear || true

# Run migrations (safe to run every start)
php artisan migrate --force || true

# Start the app
exec php artisan serve --host=0.0.0.0 --port=${PORT}
