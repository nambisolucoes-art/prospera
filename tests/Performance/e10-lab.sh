#!/bin/bash
set -euo pipefail

# Laboratório descartável da E10: PostgreSQL 18 em tmpfs, FrankenPHP e Mailpit em rede interna.
# Nunca usa docker compose, volumes ou containers oficiais. Só remove recursos com o rótulo e10.
source_dir=$(realpath "$(dirname "$0")/../..")
prefix=${E10_PREFIX:-prospera-e10-lab-$$}
port=${E10_PORT:-18110}
workers=${E10_WORKERS:-1}
action=${1:-up}
[[ "$prefix" == prospera-e10-lab-* ]] || { echo 'Prefixo de laboratório inválido'; exit 1; }

if [ "$action" = down ]; then
    for kind in app db mail; do
        name="$prefix-$kind"
        if [ "$(docker inspect -f '{{ index .Config.Labels "prospera.lab" }}' "$name" 2>/dev/null || true)" = e10 ]; then
            docker rm -f "$name" >/dev/null
        fi
    done
    docker network rm "$prefix" "$prefix-browser" >/dev/null 2>&1 || true
    exit
fi

docker network create --internal --label prospera.lab=e10 "$prefix" >/dev/null
docker network create --label prospera.lab=e10 "$prefix-browser" >/dev/null

docker run -d --rm --label prospera.lab=e10 --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD=synthetic-e10 -e POSTGRES_DB=e10 postgres:18 >/dev/null

# Mailpit sem relay: recebe o SMTP da aplicação e expõe a API só no loopback do host.
docker run -d --rm --label prospera.lab=e10 --name "$prefix-mail" --network "$prefix" --network-alias mail \
    axllent/mailpit:v1.27 >/dev/null
docker network connect "$prefix-browser" "$prefix-mail"

for attempt in {1..30}; do
    if docker exec "$prefix-db" pg_isready -U postgres -d e10 >/dev/null 2>&1; then break; fi
    sleep 1
done

docker run -d --rm --label prospera.lab=e10 --name "$prefix-app" --network "$prefix" \
    -p "127.0.0.1:$port:8000" --entrypoint sh -v "$source_dir:/source:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    -e APP_URL="http://127.0.0.1:$port" -e DB_CONNECTION=pgsql -e DB_HOST=pg -e DB_DATABASE=e10 \
    -e DB_USERNAME=postgres -e DB_PASSWORD=synthetic-e10 -e SESSION_DRIVER=database -e CACHE_STORE=file \
    -e MAIL_MAILER=smtp -e MAIL_HOST=mail -e MAIL_PORT=1025 -e MAIL_SCHEME=smtp \
    -e MAIL_FROM_ADDRESS=prospera@e10.test -e BCRYPT_ROUNDS=4 -e LOG_CHANNEL=stderr \
    -e PROSPERA_MULTIPLE_ACCOUNTS=true -e E10_METRICS="${E10_METRICS:-0}" -e E10_WORKERS="$workers" -e E10_OCTANE_ARGS="${E10_OCTANE_ARGS-}" \
    prospera-app:latest -c '
        set -eu
        for path in app bootstrap config database docker docs resources routes public tests; do
            rm -rf "/app/$path"
            cp -R "/source/$path" "/app/$path"
        done
        cp /source/phpunit.xml /app/
        rm -f /app/.env /app/public/hot /app/bootstrap/cache/config.php /app/bootstrap/cache/routes*.php
        cd /app
        # Métricas HTTP só na cópia descartável do bootstrap, nunca no arquivo oficial.
        if [ "$E10_METRICS" = 1 ]; then
            sed -i "/withMiddleware(function/a\\        \$middleware->prepend(\\\\Tests\\\\Performance\\\\OctaneMetrics::class);" bootstrap/app.php
            grep -q OctaneMetrics bootstrap/app.php
        fi
        php artisan migrate --force
        php artisan permissions:sync
        php artisan audit:check
        php tests/Performance/e10/fixtures.php
        php artisan organization:check
        exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers="$E10_WORKERS" --max-requests=20000 $E10_OCTANE_ARGS
    ' >/dev/null
docker network connect "$prefix-browser" "$prefix-app"

mail_ip=$(docker inspect -f "{{ (index .NetworkSettings.Networks \"$prefix-browser\").IPAddress }}" "$prefix-mail")
echo "Laboratório: $prefix, app http://127.0.0.1:$port, Mailpit http://$mail_ip:8025 (rede $prefix-browser)"
echo 'Aguarde /up antes de executar os clientes. Não exponha este laboratório fora do loopback.'
