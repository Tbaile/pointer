# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1.13.1-php8.5.12-alpine AS base

RUN apk add --no-cache openssh-client-default

RUN install-php-extensions intl pcntl pdo_pgsql zip

RUN addgroup -g 1000 pointer \
    && adduser -D -u 1000 -G pointer pointer \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R pointer:pointer /config/caddy /data/caddy

WORKDIR /app

EXPOSE 80 443 443/udp

FROM base AS dev

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --link --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

COPY --chmod=755 containers/php/entrypoint.dev.sh /usr/local/bin/pointer-entrypoint

USER pointer

ENTRYPOINT ["pointer-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

FROM base AS vendor

COPY --link --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.json
COPY composer.lock composer.lock

RUN --mount=type=cache,target=/root/.cache/composer \
    composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY artisan artisan
COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database/migrations database/migrations
COPY database/seeders database/seeders
COPY public public
COPY resources resources
COPY routes routes

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer check-platform-reqs --no-dev \
    && composer dump-autoload --optimize --no-dev

FROM node:24.20.0-alpine AS assets

WORKDIR /app

COPY package.json package.json
COPY package-lock.json package-lock.json
COPY .npmrc .npmrc

RUN --mount=type=cache,target=/root/.npm \
    npm ci

COPY vite.config.js vite.config.js
COPY app app
COPY resources resources
COPY --from=vendor /app/vendor vendor

RUN npm run build

FROM base AS ci

COPY --link --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.json
COPY composer.lock composer.lock

RUN --mount=type=cache,target=/root/.cache/composer \
    composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY artisan artisan
COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database/factories database/factories
COPY database/migrations database/migrations
COPY database/seeders database/seeders
COPY public public
COPY resources resources
COPY routes routes
COPY tests tests
COPY phpunit.xml phpunit.xml
COPY .env.example .env
COPY --from=assets /app/public/build public/build

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --optimize \
    && php artisan key:generate \
    && php artisan passport:keys \
    && chown -R pointer:pointer storage bootstrap/cache

USER pointer

CMD ["php", "artisan", "test"]

FROM base AS prod

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link --from=vendor /app /app
COPY --link --from=assets /app/public/build /app/public/build

COPY --chmod=755 containers/php/entrypoint.prod.sh /usr/local/bin/pointer-entrypoint

RUN chown -R pointer:pointer storage bootstrap/cache

USER pointer

ENTRYPOINT ["pointer-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
