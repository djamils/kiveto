FROM php:8.5.0-fpm-bookworm

WORKDIR /var/www/html

ENV APP_ENV=dev
ENV APP_DEBUG=1

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install intl pdo_mysql zip

# Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
