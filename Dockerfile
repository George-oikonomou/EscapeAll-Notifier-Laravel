FROM php:8.3-cli

# system deps
RUN apt-get update && apt-get install -y \
    git zip unzip curl nodejs npm netcat-openbsd libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# playwright + browser system deps
RUN npx playwright install-deps || apt-get install -y \
    libnspr4 \
    libnss3 \
    libatk1.0-0t64 \
    libatk-bridge2.0-0t64 \
    libcups2t64 \
    libxkbcommon0 \
    libatspi2.0-0t64 \
    libxdamage1 \
    libcairo2 \
    libpango-1.0-0 \
    libasound2t64

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# ensure entrypoint.sh is executable
RUN chmod +x /var/www/html/entrypoint.sh

# PHP + JS deps
RUN composer install && npm install && npm run build || true

# Node automation setup
WORKDIR /var/www/html/node/automation
RUN npm install playwright-extra puppeteer-extra-plugin-stealth && npx playwright install chromium || true

# back to app root
WORKDIR /var/www/html

EXPOSE 8000
ENTRYPOINT ["sh", "./entrypoint.sh"]

