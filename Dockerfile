FROM node:22-alpine AS frontend
WORKDIR /build
COPY package*.json ./
RUN npm ci
COPY tsconfig.json index.html ./
COPY frontend ./frontend
COPY public ./public
RUN npm run build
FROM php:8.4-apache AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev unzip && docker-php-ext-install pdo_mysql mbstring && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY --from=frontend /build/dist /var/www/html
COPY api /var/www/html/api
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
RUN printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf && a2enconf servername

FROM runtime AS quality
RUN composer install --no-interaction --prefer-dist
COPY phpunit.xml phpstan.neon /var/www/html/
COPY tests/Unit /var/www/html/tests/Unit
CMD ["composer", "check"]
