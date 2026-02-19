#!/bin/sh
set -e

php /app/artisan migrate --force --isolated

exec "$@"
