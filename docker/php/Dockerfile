FROM php:8.5-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

# Xdebug inflates memory usage; the 128M CLI default isn't enough for cache:clear locally.
RUN printf "memory_limit=512M\n" > /usr/local/etc/php/conf.d/99-memory.ini

# `zip` is required by Codeception's WebDriver client (php-webdriver); chromium + chromedriver
# let the acceptance suite drive a headless browser in this container (`make ee`). Nothing else
# in the stack depends on them.
RUN apk --no-cache add \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        autoconf make gcc g++ linux-headers \
    && docker-php-ext-install pdo_pgsql intl zip \
    && pecl install xdebug redis \
    && docker-php-ext-enable xdebug redis \
    && apk del autoconf make gcc g++ linux-headers postgresql-dev icu-dev libzip-dev \
    && apk --no-cache add libpq icu-libs libzip git unzip \
    && apk --no-cache add chromium chromium-chromedriver

WORKDIR /home/docker/src
