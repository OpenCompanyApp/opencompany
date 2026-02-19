#!/bin/sh
set -e

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    export APP_KEY=$(php /app/artisan key:generate --show)
    echo "Generated APP_KEY: $APP_KEY"
fi

# Cache config with runtime env vars
php /app/artisan config:cache
php /app/artisan migrate --force --isolated

exec "$@"
