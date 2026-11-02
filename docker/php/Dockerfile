# syntax=docker/dockerfile:1.7
# Build context is the repository root (see docker-compose.yml).

# ---------------------------------------------------------------------------
# base: php-fpm with the extensions the API needs
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache icu-libs libpq libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql pgsql zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=0755 docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

WORKDIR /var/www/api

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# development: code is bind-mounted, opcache re-checks files on every request
# ---------------------------------------------------------------------------
FROM base AS development

# Match www-data to the host user so files written in the bind mount stay editable.
ARG UID=1000
ARG GID=1000
RUN apk add --no-cache shadow \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/opcache-dev.ini /usr/local/etc/php/conf.d/zz-opcache.ini

USER www-data

# ---------------------------------------------------------------------------
# vendor: production Composer dependencies, cached apart from the app code
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# ---------------------------------------------------------------------------
# production: code baked into the image, opcache never re-checks files
# ---------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache.ini

COPY --from=vendor /var/www/api/vendor ./vendor
COPY api/ ./

# dump-autoload also runs `artisan package:discover` (post-autoload-dump script).
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
