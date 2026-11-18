#!/bin/sh
set -eu

environment_file=storage/app/.env
if [ ! -s "$environment_file" ]; then
    key=$(php artisan key:generate --show --no-ansi)
    (
        umask 077
        printf 'APP_KEY=%s\n' "$key" > "$environment_file.tmp"
        mv "$environment_file.tmp" "$environment_file"
    )
fi
ln -sfn "$environment_file" .env

php artisan migrate --force --no-interaction
exec "$@"
