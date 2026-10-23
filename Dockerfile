FROM node:22-alpine AS frontend
WORKDIR /build
COPY package*.json ./
RUN npm ci
COPY tsconfig.json index.html ./
COPY frontend ./frontend
COPY public ./public
RUN npm run build
FROM php:8.4-apache
RUN docker-php-ext-install pdo_mysql
COPY --from=frontend /build/dist /var/www/html
COPY api /var/www/html/api
RUN printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf && a2enconf servername
