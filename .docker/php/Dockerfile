FROM composer:2 AS composer

FROM php:8.2-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl mbstring mysqli pdo_mysql zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY ./.docker/php/fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./.docker/php/entrypoint.sh /usr/local/bin/project-entrypoint

RUN sed -i 's/\r$//' /usr/local/bin/project-entrypoint \
    && chmod +x /usr/local/bin/project-entrypoint

CMD ["/usr/local/bin/project-entrypoint"]

EXPOSE 9000
