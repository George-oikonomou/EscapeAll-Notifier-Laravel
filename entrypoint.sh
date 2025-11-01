#!/bin/sh
set -e

echo "Waiting for MySQL at $DB_HOST:$DB_PORT..."
until nc -z "$DB_HOST" "$DB_PORT"; do
  sleep 1
done
echo "Database is ready."

# .env
if [ ! -f .env ]; then
  echo ".env not found, creating..."
  cp .env.example .env
else
  echo ".env already exists."
fi

# composer dependencies
if [ ! -d vendor ]; then
  echo "Installing composer dependencies..."
  composer install
else
  echo "Vendor folder exists, skipping composer install."
fi

# npm dependencies
if [ ! -d node_modules ]; then
  echo "Installing npm dependencies..."
  npm install
else
  echo "node_modules folder exists, skipping npm install."
fi


# Skip setup if APP_KEY exists and is non-empty
APP_KEY_VALUE=$(grep '^APP_KEY=' .env | cut -d'=' -f2)
if [ -n "$APP_KEY_VALUE" ]; then
  echo "Existing APP_KEY detected. Skipping migrations and seeders."
else
  echo "No APP_KEY found. Generating key and running initial setup..."
  php artisan key:generate --force
  php artisan migrate --force
  php artisan db:seed --force
  echo "Initial setup complete."
fi




# start Laravel dev server
exec php artisan serve --host=0.0.0.0 --port=8000
