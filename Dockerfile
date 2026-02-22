FROM php:8.3-cli

# system deps
RUN apt-get update && apt-get install -y \
    git zip unzip curl nodejs npm netcat-openbsd libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd


COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# ensure entrypoint.sh is executable
RUN chmod +x /var/www/html/entrypoint.sh

# install PHP + JS deps
RUN composer install && npm install && npm run build || true

# install node/scripts deps and Playwright with ALL system dependencies
RUN cd node/scripts && npm install && npx playwright install --with-deps chromium || true

# install node/automation deps and Playwright with ALL system dependencies
RUN cd node/automation && npm install && npx playwright install --with-deps chromium || true

EXPOSE 8000
ENTRYPOINT ["sh", "./entrypoint.sh"]

