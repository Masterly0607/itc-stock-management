# Why do we need to use Dockerfile with php? Why don't we pull from docker hub and use it? If you just use image from Docker Hub:It’s empty → only PHP basic., No Composer inside, No Laravel extensions, No custom php.ini, No startup command.


FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git curl zip unzip libzip-dev oniguruma-dev icu-dev mariadb-client bash

RUN docker-php-ext-configure intl
RUN docker-php-ext-install pdo pdo_mysql intl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY ./docker/app/php.ini /usr/local/etc/php/conf.d/99-custom.ini

WORKDIR /var/www/html

# start Laravel’s built-in server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]