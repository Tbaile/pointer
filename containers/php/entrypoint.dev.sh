#!/bin/sh
set -e

mkdir -p ~/.ssh
chmod 700 ~/.ssh

if [ -n "$POINTER_SUPPORT_IDENTITY" ]; then
    printf '%s\n' "$POINTER_SUPPORT_IDENTITY" > "$POINTER_SUPPORT_IDENTITY_FILE"
    chmod 600 "$POINTER_SUPPORT_IDENTITY_FILE"
fi

if [ -n "$POINTER_SUPPORT_PORT" ]; then
    printf 'Host %s\n    Port %s\n' "$POINTER_SUPPORT_HOST" "$POINTER_SUPPORT_PORT" > ~/.ssh/config
fi

# Setup runs only when starting the web server (default CMD starts with --config)
if [ "${1#-}" != "$1" ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
    composer install --no-interaction
    if grep -q '^APP_KEY=$' .env; then
        php artisan key:generate
    fi
    if [ ! -f storage/oauth-private.key ]; then
        php artisan passport:keys
    fi

    php artisan migrate --force
    php artisan db:seed --force
fi

exec docker-php-entrypoint "$@"
