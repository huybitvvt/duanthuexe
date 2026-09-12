FROM composer:2.2 AS composer

FROM php:7.4-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN set -eux; \
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
    && docker-php-ext-install -j"$(nproc)" bcmath gd mbstring pdo_pgsql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

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
