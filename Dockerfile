# syntax=docker/dockerfile:1

FROM node:20-bookworm AS assets

WORKDIR /theme
COPY web/app/themes/remote-leverage/package.json \
     web/app/themes/remote-leverage/package-lock.json ./
RUN npm ci
COPY web/app/themes/remote-leverage/ ./
RUN npm run build

FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
      curl \
      git \
      libfreetype6-dev \
      libicu-dev \
      libjpeg62-turbo-dev \
      libonig-dev \
      libpng-dev \
      libxml2-dev \
      libzip-dev \
      unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
      bcmath \
      exif \
      gd \
      intl \
      mysqli \
      opcache \
      pdo_mysql \
      soap \
      zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS build

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN mkdir -p web/app/plugins web/app/mu-plugins web/app/themes \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

COPY web/app/themes/remote-leverage/composer.json \
     web/app/themes/remote-leverage/composer.lock \
     web/app/themes/remote-leverage/
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
      --working-dir=web/app/themes/remote-leverage --no-scripts

COPY . .
COPY --from=assets /theme/public web/app/themes/remote-leverage/public
RUN composer dump-autoload --optimize --no-dev \
    && composer dump-autoload --optimize --no-dev --working-dir=web/app/themes/remote-leverage

FROM php-base AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends nginx procps \
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp \
    && rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf \
    && mkdir -p /var/www/html/web/app/uploads \
    && chown -R www-data:www-data /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/wordpress.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && rm -f /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /app /var/www/html

ENV WP_ENV=staging
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
