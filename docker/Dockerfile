FROM php:8.4-cli-bookworm AS base

ARG TZ=Europe/Berlin
ENV TZ=${TZ}
ENV APP_ENV=production

RUN apt-get update \
    && apt-get install -y git unzip libpq-dev tzdata libzip-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_pgsql pgsql mysqli pdo_mysql \
    && ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo ${TZ} > /etc/timezone \
    && echo "date.timezone=${TZ}" > /usr/local/etc/php/conf.d/timezone.ini \
    && rm -rf /var/lib/apt/lists/*

FROM base AS builder

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}

WORKDIR /app

#RUN groupadd -g 33 www-data && useradd -u 33 -g www-data www-data

COPY --chown=www-data:www-data composer.json /app/
COPY --chown=www-data:www-data ../bin /app/bin
COPY --chown=www-data:www-data ../.env /app/.env
COPY --chown=www-data:www-data ../index.php /app/index.php
COPY --chown=www-data:www-data ../src /app/src
COPY --chown=www-data:www-data tests /app/tests
COPY --chown=www-data:www-data phpunit.xml /app/phpunit.xml

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && if [ "${APP_ENV}" = "development" ]; then \
         composer install; \
       else \
         composer install --no-dev --optimize-autoloader --prefer-dist; \
       fi

FROM base AS final

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}

WORKDIR /app

COPY --from=builder /app /app
COPY --from=builder /usr/bin/composer /usr/bin/composer

CMD ["php", "index.php"]
