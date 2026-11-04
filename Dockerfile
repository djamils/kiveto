# syntax=docker/dockerfile:1

# Production images of Kiveto, built once per commit and promoted by digest
# (linux/arm64 for AWS, linux/amd64 for local checks):
#
#   php      PHP-FPM, the application, its vendors (no dev) and its warmed
#            prod cache. Also runs the workers and the console.
#   nginx    the compiled public/ behind Nginx, on 8080, for every host.
#   sandbox  php plus the dev dependencies: only to load the Foundry stories
#            on STAGING and DEMO (kiveto-seed).
#
#   docker buildx build --platform linux/arm64 --target php -t kiveto/php .
#
# Secrets never enter an image: the deployment provides .env.runtime, the
# JWT keypair (mounted on config/jwt) and var/storage (a volume). The dev
# stack keeps its own images (docker/php-fpm, docker/nginx).

ARG PHP_BASE=php:8.5.12-fpm-bookworm@sha256:75dc898a8861a777f14c57b2afbb811a848735701593cbc354e4194807a88f3e
ARG COMPOSER_BASE=composer:2.11.1@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c
ARG NGINX_BASE=nginx:1.30.6-alpine@sha256:0e52bd054126cdc040b1276c0af347aeb02a41b9cfe305d6094767ac30e97949

FROM ${COMPOSER_BASE} AS composer

# ---------------------------------------------------------------------------
# runtime: PHP with the extensions and settings of the application, no code.
# ---------------------------------------------------------------------------
FROM ${PHP_BASE} AS runtime

# The extensions of the dev image (docker/php-fpm/Dockerfile), without
# Xdebug, plus pcntl: messenger:consume then stops between two messages on
# SIGTERM instead of dying mid-message. OPcache is built into PHP 8.5. The
# build packages are removed, their runtime libraries kept. cgi-fcgi
# (libfcgi-bin) serves the healthcheck.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends libicu-dev libzip-dev; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_mysql zip; \
    apt-mark auto '.*' > /dev/null; \
    [ -z "$savedAptMark" ] || apt-mark manual $savedAptMark; \
    find /usr/local -type f -executable -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | cut -d: -f1 | sort -u | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    apt-get install -y --no-install-recommends libfcgi-bin; \
    rm -rf /var/lib/apt/lists/*; \
    for ext in bcmath intl pcntl pdo_mysql zip; do php -m | grep -qx "$ext"; done

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/production/php/kiveto.ini $PHP_INI_DIR/conf.d/zz-kiveto.ini
COPY docker/production/php/zz-kiveto.conf /usr/local/etc/php-fpm.d/zz-kiveto.conf
COPY --chmod=755 docker/production/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck

# CA bundle of Amazon RDS: DATABASE_SSL_CA points to it in production.
ADD --chmod=644 https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem /etc/ssl/certs/rds-global-bundle.pem

WORKDIR /var/www/html
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    PHP_FPM_MAX_CHILDREN=10

# ---------------------------------------------------------------------------
# build: vendors and compiled assets. Runs on the build machine's platform:
# its output (PHP code, CSS, JS) does not depend on the CPU.
# ---------------------------------------------------------------------------
FROM --platform=$BUILDPLATFORM runtime AS build
COPY --from=composer /usr/bin/composer /usr/bin/composer
# KIVETO_DOMAIN: any valid domain, in case a console command below builds the
# router; this stage is not shipped.
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    KIVETO_DOMAIN=kiveto.app
WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-progress --prefer-dist --no-interaction

COPY . .
# What `composer install` and `make assets` do in dev, for prod: bundle
# assets, importmap packages, Tailwind (minified), then the AssetMapper
# compilation into public/assets.
RUN set -eux; \
    composer dump-autoload --no-dev --classmap-authoritative --no-interaction; \
    bin/console assets:install public --no-interaction; \
    bin/console importmap:install --no-interaction; \
    php -d memory_limit=1G bin/console tailwind:build --minify --no-interaction; \
    rm -rf public/assets; \
    bin/console asset-map:compile --no-interaction; \
    mkdir /dist; \
    cp -a .env .env.prod composer.json composer.lock symfony.lock importmap.php \
        assets bin config migrations public resources src templates translations vendor \
        /dist/

# ---------------------------------------------------------------------------
# php: the application image.
# ---------------------------------------------------------------------------
FROM runtime AS php
# The code belongs to root: PHP-FPM and the workers, as www-data, cannot
# rewrite it. www-data owns what the application writes: var/ (var/storage is
# the `files` volume, var/share the `share` volume — cache pools, sessions —,
# created here so that a new volume takes www-data as its owner) and storage/
# (the pharmaceutical registry files). config/jwt is the keypair's mount point.
COPY --from=build /dist /var/www/html
COPY --from=build --chown=www-data:www-data /app/storage /var/www/html/storage
RUN set -eux; \
    mkdir -p var/storage var/share/sessions var/log config/jwt; \
    chown -R www-data:www-data var
USER www-data
# The routes read KIVETO_DOMAIN at runtime only: any valid value builds the
# same cache, which then serves every deployment.
RUN KIVETO_DOMAIN=kiveto.app bin/console cache:warmup --no-interaction \
    && test -f var/cache/prod/App_KernelProdContainer.preload.php
# The healthchecks are declared per service in deploy/compose.*.yaml: the
# workers run this image too, without PHP-FPM.
EXPOSE 9000

# ---------------------------------------------------------------------------
# sandbox: php plus the dev dependencies (Foundry), to seed STAGING and DEMO.
# ---------------------------------------------------------------------------
FROM php AS sandbox
USER root
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --chmod=755 docker/production/sandbox/kiveto-seed /usr/local/bin/kiveto-seed
COPY fixtures fixtures/
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-scripts --no-progress --prefer-dist --no-interaction \
    && composer dump-autoload --classmap-authoritative --no-interaction \
    && rm -rf /tmp/composer
USER www-data

# ---------------------------------------------------------------------------
# nginx: the compiled public/, every Kiveto host, on 8080.
# ---------------------------------------------------------------------------
FROM ${NGINX_BASE} AS nginx
COPY docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/public /var/www/html/public
EXPOSE 8080
