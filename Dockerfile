FROM php:8.3-cli

# ---- System dependencies ----
RUN apt-get update && apt-get install -y \
    git zip unzip curl nodejs npm netcat-openbsd \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# ---- Playwright deps ----
RUN apt-get install -y \
    libnspr4 \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libxkbcommon0 \
    libatspi2.0-0 \
    libxdamage1 \
    libcairo2 \
    libpango-1.0-0 \
    libasound2 \
    && npm install -g npx

# ---- Composer ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# ---- Entrypoint permissions ----
RUN chmod +x /var/www/html/entrypoint.sh

# ---- PHP + JS app deps ----
RUN composer install && npm install && npm run build || true

EXPOSE 8000
ENTRYPOINT ["sh", "./entrypoint.sh"]
