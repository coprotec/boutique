# Image de la boutique : Apache + PHP dans le conteneur, Apache de l'hôte en reverse proxy (cf. DEPLOIEMENT_VPS.md).

# ----------- BASE : PHP + Apache + extensions (utilisée telle quelle en dev, code monté en volume)
FROM php:8.3-apache AS base

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install intl opcache pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN a2enmod rewrite headers

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-boutique.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/app.coprotec.net/httpdocs

# ----------- ASSETS : compilation Webpack Encore (Node uniquement au build)
FROM node:22-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY webpack.config.js ./
COPY assets ./assets
RUN npm run build

# ----------- WEB : image préprod / prod
FROM base AS web
ARG APP_ENV=prod
ENV APP_ENV=${APP_ENV}

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

COPY . .
COPY --from=assets /app/public/build ./public/build

# Pas de dump-env : les secrets viennent du .env.local monté au lancement (docker-compose.*.yml).
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && mkdir -p var/cache var/log \
 && chown -R www-data:www-data var

EXPOSE 80
CMD ["apache2-foreground"]

# ----------- CRON : même code que web, tâches planifiées (docker/cron/crontab)
FROM web AS cron
RUN apt-get update && apt-get install -y cron && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY docker/cron/crontab /etc/cron.d/boutique
RUN chmod 0644 /etc/cron.d/boutique
# Les commandes lisent elles-mêmes .env + le .env.local monté : rien à transmettre par l'environnement de cron.
CMD ["cron", "-f"]
