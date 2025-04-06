FROM composer:latest AS builder

ARG APP_ENV=production

RUN apk update && apk add --no-cache libpq-dev

WORKDIR /app

COPY --chown=www-data:www-data composer.json /app/
COPY --chown=www-data:www-data ../src /app/src

COPY --chown=www-data:www-data tests /app/tests
COPY --chown=www-data:www-data phpunit.xml /app/phpunit.xml

RUN if [ "${APP_ENV}" = "development" ]; then \
    composer install; \
    else \
    composer install --no-dev --optimize-autoloader --prefer-dist; \
    fi

FROM php:8.4-cli-bookworm

ARG ENV=prod

ENV TZ=Europe/Berlin
ENV APP_ENV=${APP_ENV}

RUN apt-get update \
    && apt-get install -y git tzdata libpq-dev \
    && ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo ${TZ} > /etc/timezone \
    && echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini \
    && docker-php-ext-install pgsql mysqli pdo pdo_mysql pdo_pgsql \
    && rm -rf /var/lib/apt/list/*

WORKDIR /app

COPY --from=builder /app /app
COPY --from=builder /usr/bin/composer /usr/bin/composer

CMD ["php", "src/index.php"]
