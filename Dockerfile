FROM composer:2.2 AS composer

FROM php:7.4-apache-bullseye

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# PHP 7.4 is only published on Debian 11 (Bullseye). Bullseye reached the end
# of Debian LTS on 2026-08-31, so its live mirrors can remove packages while a
# build is running. Use the final LTS snapshot to keep package indexes and .deb
# files consistent until the application can be upgraded to a supported PHP.
ARG DEBIAN_SNAPSHOT=20260831T235959Z

RUN set -eux; \
    printf '%s\n' \
        "deb [check-valid-until=no] https://snapshot.debian.org/archive/debian/${DEBIAN_SNAPSHOT}/ bullseye main" \
        "deb [check-valid-until=no] https://snapshot.debian.org/archive/debian/${DEBIAN_SNAPSHOT}/ bullseye-updates main" \
        "deb [check-valid-until=no] https://snapshot.debian.org/archive/debian-security/${DEBIAN_SNAPSHOT}/ bullseye-security main" \
        > /etc/apt/sources.list; \
    rm -rf /etc/apt/sources.list.d/*; \
    for attempt in 1 2 3; do \
        rm -rf /var/lib/apt/lists/*; \
        if apt-get -o Acquire::ForceIPv4=true \
            -o Acquire::Retries=5 \
            -o Acquire::http::Timeout=60 \
            -o Acquire::https::Timeout=60 \
            update --allow-releaseinfo-change; then \
            break; \
        fi; \
        if [ "${attempt}" = "3" ]; then exit 100; fi; \
    done; \
    apt-get -o Acquire::ForceIPv4=true \
        -o Acquire::Retries=5 \
        -o Acquire::http::Timeout=60 \
        -o Acquire::https::Timeout=60 \
        install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd mbstring opcache pdo_pgsql zip \
    && a2enmod deflate expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache-performance.conf /etc/apache2/conf-available/himoto-performance.conf
COPY docker/php-performance.ini /usr/local/etc/php/conf.d/99-himoto-performance.ini

RUN a2enconf himoto-performance

WORKDIR /var/www/html
COPY . .

RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/render-start.sh

EXPOSE 10000

CMD ["docker/render-start.sh"]
