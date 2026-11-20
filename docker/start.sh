#!/bin/sh
set -eu

environment_file=storage/app/.env
if [ -f .env ] && [ ! -L .env ]; then
    # Desenvolvimento (make dev): o .env é o arquivo do host montado no container e não pode virar symlink.
    grep -q '^APP_KEY=.' .env || php artisan key:generate --force --no-interaction
elif [ ! -s "$environment_file" ]; then
    key=$(php artisan key:generate --show --no-ansi)
    (
        umask 077
        printf 'APP_KEY=%s\n' "$key" > "$environment_file.tmp"
        mv "$environment_file.tmp" "$environment_file"
    )
fi
if [ ! -f .env ] || [ -L .env ]; then
    ln -sfn "$environment_file" .env
fi

php artisan migrate --force --no-interaction
php artisan permissions:sync --no-interaction
php artisan audit:check --no-interaction
exec "$@"
