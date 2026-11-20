#!/bin/bash
set -euo pipefail

# Ambiente descartável para validação visual e testes manuais da E04, com PostgreSQL em tmpfs.
# Uso: bash tests/Performance/e04-stack.sh up [porta] | down
# Exige `npm run build` na cópia de trabalho. Só a porta do app é publicada, em 127.0.0.1.
# E06_CONTEXT=1 acrescenta a conta Grupo Gartan e os perfis da E06.
action=${1:-up}
port=${2:-8090}
source_dir=$(realpath "$(dirname "$0")/../..")
fixture_dir=$(realpath "$(dirname "$0")")
prefix=${STACK_PREFIX:-prospera-e04-stack}

if [ "$action" = down ]; then
    docker rm -f "$prefix-app" "$prefix-db" >/dev/null 2>&1 || true
    docker network rm "$prefix" >/dev/null 2>&1 || true
    exit
fi

docker network create "$prefix" >/dev/null
docker run -d --rm --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD=e04-synthetic -e POSTGRES_DB=e04 \
    postgres:18 >/dev/null
for attempt in {1..30}; do
    if docker exec "$prefix-db" pg_isready -h 127.0.0.1 -U postgres -d e04 >/dev/null 2>&1; then break; fi
    sleep 1
done

docker run -d --rm --name "$prefix-app" --network "$prefix" -p "127.0.0.1:$port:8000" --entrypoint sh \
    -v "$source_dir:/source:ro" -v "$fixture_dir:/performance:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e APP_URL="http://127.0.0.1:$port" \
    -e DB_CONNECTION=pgsql -e DB_HOST=pg -e DB_DATABASE=e04 -e DB_USERNAME=postgres -e DB_PASSWORD=e04-synthetic \
    -e SESSION_DRIVER=database -e CACHE_STORE=file -e MAIL_MAILER=log -e BCRYPT_ROUNDS=4 -e LOG_CHANNEL=stderr \
    prospera-app:latest -c '
        set -eu
        for path in app bootstrap config database docs resources routes public; do
            rm -rf "/app/$path"
            cp -R "/source/$path" "/app/$path"
        done
        rm -f /app/.env /app/public/hot /app/bootstrap/cache/config.php /app/bootstrap/cache/routes*.php
        php artisan migrate --force
        php artisan permissions:sync
        php artisan audit:check
        php /performance/e04-fixtures.php
        if [ "$1" = 1 ]; then
            php /performance/e06-fixtures.php
        fi
        php artisan organization:check
        exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=2
    ' setup "${E06_CONTEXT:-0}" >/dev/null

for attempt in {1..60}; do
    if curl -fsS "http://127.0.0.1:$port/up" >/dev/null 2>&1; then
        echo "Ambiente em http://127.0.0.1:$port (senha dos usuários: password)"
        exit
    fi
    sleep 1
done

docker logs --tail 80 "$prefix-app"
exit 1
