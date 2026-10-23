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
    php artisan migrate --force
    php artisan db:seed --force
    php artisan optimize
fi

exec docker-php-entrypoint "$@"
