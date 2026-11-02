#!/bin/sh
# Entrypoint for the php, scheduler and migrate containers.
set -e

cd /var/www/api

if [ "${APP_ENV:-local}" = "production" ]; then
    # Config comes from the container environment; cache it once per start.
    php artisan optimize --no-interaction
else
    # Local run: the code is bind-mounted, so prepare it on first start.
    [ -f .env ] || cp .env.example .env
    [ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist --no-progress
    grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force --no-interaction
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

# Seeders must be safe to run again (updateOrCreate / firstOrCreate).
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

exec "$@"
