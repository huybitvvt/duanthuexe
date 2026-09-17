FROM composer:2.2 AS composer

FROM php:7.4-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN set -eux; \
    sed -i '/security/d' /etc/apt/sources.list || true; \
    rm -f /etc/apt/sources.list.d/*security* 2>/dev/null || true; \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until; \
    for attempt in 1 2 3; do \
        rm -rf /var/lib/apt/lists/*; \
        if apt-get -o Acquire::ForceIPv4=true \
            -o Acquire::Retries=5 \
            -o Acquire::http::Timeout=60 \
            -o Acquire::https::Timeout=60 \
            update --allow-releaseinfo-change; then \
            break; \
        fi; \
        if [ "${attempt}" = "1" ]; then \
            echo "deb http://archive.debian.org/debian bullseye main" > /etc/apt/sources.list; \
            echo "deb http://archive.debian.org/debian bullseye-updates main" >> /etc/apt/sources.list; \
        fi; \
        if [ "${attempt}" = "2" ]; then \
            echo "deb [check-valid-until=no] http://snapshot.debian.org/archive/debian/20260830T000000Z/ bullseye main" > /etc/apt/sources.list; \
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
