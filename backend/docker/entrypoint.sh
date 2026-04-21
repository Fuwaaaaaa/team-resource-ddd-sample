#!/bin/sh
set -e

# Ensure .env exists (copy from .env.example on first boot)
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Generate APP_KEY if missing
if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

# Wait for postgres (healthcheck on compose side should cover this, but belt + suspenders)
until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-app}" >/dev/null 2>&1; do
  echo "waiting for postgres..."
  sleep 1
done

php artisan migrate --force
php artisan db:seed --force

exec "$@"
