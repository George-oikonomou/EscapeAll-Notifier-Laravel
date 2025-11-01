#!/bin/sh
set -eu

APP_DIR=/var/www/html
AUTO_DIR=$APP_DIR/node/automation

echo "Waiting for MySQL at ${DB_HOST:?}:${DB_PORT:?}..."
until nc -z "$DB_HOST" "$DB_PORT"; do sleep 1; done
echo "Database is ready."

# Work only in app dir
cd "$APP_DIR"

# .env
[ -f "$APP_DIR/.env" ] || { echo ".env not found, creating..."; cp "$APP_DIR/.env.example" "$APP_DIR/.env"; }

# Composer (optional: lock-aware)
if [ -f "$APP_DIR/composer.lock" ]; then
  composer install --no-interaction --prefer-dist --no-progress
else
  composer update   --no-interaction --prefer-dist --no-progress
fi

# Root JS deps
if [ -f "$APP_DIR/package.json" ]; then
  if [ -f "$APP_DIR/package-lock.json" ]; then npm ci --no-progress; else npm install --no-progress; fi
fi

# Automation deps + extras + browser, then return to app dir
if [ -d "$AUTO_DIR" ] && [ -f "$AUTO_DIR/package.json" ]; then
  cd "$AUTO_DIR"
  if [ -f "$AUTO_DIR/package-lock.json" ]; then npm ci --no-progress; else npm install --no-progress; fi
  npm ls -s playwright-extra >/dev/null 2>&1 || npm install --no-progress --save-exact playwright-extra
  npm ls -s puppeteer-extra-plugin-stealth >/dev/null 2>&1 || npm install --no-progress --save-exact puppeteer-extra-plugin-stealth
  npx playwright install chromium
  cd "$APP_DIR"
fi

# Laravel setup
APP_KEY_VALUE=$(grep '^APP_KEY=' "$APP_DIR/.env" | cut -d'=' -f2-)
if [ -z "$APP_KEY_VALUE" ]; then
  php artisan key:generate --force
  php artisan migrate --force || true
  php artisan db:seed --force || true
fi

# Single final exec: prefer provided CMD, else serve
if [ "$#" -gt 0 ]; then
  exec "$@"
else
  exec php artisan serve --host=0.0.0.0 --port=8000
fi
