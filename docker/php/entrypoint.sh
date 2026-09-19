#!/bin/sh
set -e

cd /var/www/html

# Install PHP dependencies if vendor is missing
if [ ! -d "vendor" ]; then
  echo "==> Installing composer dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Ensure .env exists
if [ ! -f ".env" ]; then
  cp .env.example .env
fi

# Generate app key if missing
if ! grep -q "^APP_KEY=base64" .env; then
  php artisan key:generate --force
fi

# Ensure Laravel's writable dirs are owned by and writable for the fpm user
# (www-data). This corrects any root-owned files left by manual commands.
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# Wait for the database to accept connections, then migrate + seed.
echo "==> Waiting for database..."
tries=0
until php artisan migrate --force --seed 2>/dev/null; do
  tries=$((tries + 1))
  if [ "$tries" -ge 30 ]; then
    echo "Database not ready after 30 tries; continuing without migrations."
    break
  fi
  echo "   database not ready yet (attempt $tries), retrying in 2s..."
  sleep 2
done

exec "$@"
