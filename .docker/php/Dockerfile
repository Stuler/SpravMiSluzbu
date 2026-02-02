FROM thecodingmachine/php:8.2-v4-fpm

WORKDIR /var/www/html

# Ensure Xdebug is available
ENV PHP_EXTENSION_XDEBUG=1

# Force a valid FPM pool config (fixes: "user has not been defined")
COPY ./.docker/php/fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

# (optional) copy app AFTER config
COPY ./ /var/www/html/
