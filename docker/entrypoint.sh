#!/bin/sh
set -e

# Bootstrap storage directory structure (volume may be empty on first deploy)
mkdir -p /app/storage/app/private \
         /app/storage/app/public/{avatars,charts,mermaid,plantuml,svg,typst,vegalite} \
         /app/storage/framework/{cache/data,sessions,testing,views} \
         /app/storage/logs \
         /app/storage/pail

# Fix permissions (volume mount overrides build-time chmod/chown)
chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Create public storage symlink
php /app/artisan storage:link --force --quiet

# Auto-generate APP_KEY if not provided, persist to storage volume
KEY_FILE="/app/storage/.app_key"
if [ -z "$APP_KEY" ]; then
    if [ -f "$KEY_FILE" ]; then
        export APP_KEY=$(cat "$KEY_FILE")
    else
        export APP_KEY=$(php /app/artisan key:generate --show)
        echo "$APP_KEY" > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
    fi
fi

# Cache config with runtime env vars
php /app/artisan config:cache

# Run migrations
php /app/artisan migrate --force --isolated

# Sync Telegram bot commands + profile photo (if configured)
php /app/artisan telegram:sync --quiet 2>/dev/null || true

exec "$@"
