FROM php:8.3-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
  git unzip libzip-dev libpng-dev libicu-dev \
  && docker-php-ext-install pdo pdo_mysql zip intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY src/ .
RUN composer install --no-dev --optimize-autoloader

# copy start script from repo root
COPY start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
