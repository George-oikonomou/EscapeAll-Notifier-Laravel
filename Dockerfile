FROM php:8.3-cli

# system deps
RUN apt-get update && apt-get install -y \
    git zip unzip curl nodejs npm netcat-openbsd libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd


COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# install PHP + JS deps
RUN composer install && npm install && npm run build || true

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
