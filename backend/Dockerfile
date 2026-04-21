FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        git \
        unzip \
        libpq-dev \
        postgresql-client \
        icu-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql intl mbstring bcmath opcache \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-progress || \
    composer install --no-interaction --no-scripts --prefer-dist --no-progress --no-dev

COPY . .

RUN composer dump-autoload --optimize

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
