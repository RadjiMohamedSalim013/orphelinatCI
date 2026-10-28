# OrphaMap CI — image de production (Render, ou tout hébergeur Docker)
#
# 1. « build » : dépendances de production, CSS Tailwind minifié, assets compilés
# 2. image finale : PHP 8.2 + Apache, sans outils de construction

FROM php:8.2-cli-bookworm AS build

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*

WORKDIR /app
ENV APP_ENV=prod APP_DEBUG=0 COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --optimize \
    && php bin/console tailwind:build --minify \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && rm -rf var/cache var/tailwind var/log


FROM php:8.2-apache-bookworm

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd intl opcache

# Apache : racine sur public/, .htaccess actifs, compression et en-têtes
RUN a2enmod rewrite headers deflate expires \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!Listen 80!Listen ${PORT}!' /etc/apache2/ports.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf
COPY docker/apache.conf /etc/apache2/conf-enabled/orphamap.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/orphamap.ini

WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/orphamap-entrypoint
# Retire d'éventuels retours chariot Windows puis rend le script exécutable.
RUN tr -d '\015' < /usr/local/bin/orphamap-entrypoint > /tmp/entrypoint \
    && mv /tmp/entrypoint /usr/local/bin/orphamap-entrypoint \
    && chmod 755 /usr/local/bin/orphamap-entrypoint

ENV APP_ENV=prod APP_DEBUG=0 PORT=10000
EXPOSE 10000

ENTRYPOINT ["orphamap-entrypoint"]
CMD ["apache2-foreground"]
