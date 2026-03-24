FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    bash \
    git \
    unzip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

ENTRYPOINT ["php"]
CMD ["bin/vending-machine"]
