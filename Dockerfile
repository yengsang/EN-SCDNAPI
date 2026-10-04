FROM node:22-bookworm-slim AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*
COPY deploy/aws/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/aws/php.ini /usr/local/etc/php/conf.d/explorer.ini
COPY selfhost /var/www/app/selfhost
COPY --from=frontend /build/dist/selfhost /var/www/app/selfhost/public
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s CMD php -r 'exit(@file_get_contents("http://127.0.0.1/healthz") === false ? 1 : 0);'
