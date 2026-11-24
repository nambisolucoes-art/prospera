#!/bin/bash
set -euo pipefail

# Somente recursos descartáveis próprios. Nunca usa docker compose nem volumes oficiais.
source_dir=$(realpath "$(dirname "$0")/../..")
prefix=${E09_PREFIX:-prospera-e09-lab-$$}
port=${E09_PORT:-18099}
action=${1:-up}
[[ "$prefix" == prospera-e09-lab-* ]] || { echo 'Prefixo de laboratório inválido'; exit 1; }
if [ "$action" = down ]; then
    for kind in app db; do
        name="$prefix-$kind"
        if [ "$(docker inspect -f '{{ index .Config.Labels "prospera.lab" }}' "$name" 2>/dev/null || true)" = e09 ]; then
            docker rm -f "$name" >/dev/null
        fi
    done
    docker network rm "$prefix" "$prefix-browser" >/dev/null 2>&1 || true
    exit
fi

docker network create --internal "$prefix" >/dev/null
docker network create "$prefix-browser" >/dev/null
docker run -d --rm --label prospera.lab=e09 --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD=synthetic-e09 -e POSTGRES_DB=e09 postgres:18 >/dev/null
for attempt in {1..30}; do
    if docker exec "$prefix-db" pg_isready -U postgres -d e09 >/dev/null; then break; fi
    sleep 1
done
docker run -d --rm --label prospera.lab=e09 --name "$prefix-app" --network "$prefix" \
    -p "127.0.0.1:$port:8000" --entrypoint sh -v "$source_dir:/source:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    -e APP_URL="http://127.0.0.1:$port" -e DB_CONNECTION=pgsql -e DB_HOST=pg -e DB_DATABASE=e09 \
    -e DB_USERNAME=postgres -e DB_PASSWORD=synthetic-e09 -e SESSION_DRIVER=database -e CACHE_STORE=file \
    -e MAIL_MAILER=array -e BCRYPT_ROUNDS=4 -e LOG_CHANNEL=stderr -e PROSPERA_MULTIPLE_ACCOUNTS=true \
    -e E09_METRICS="${E09_METRICS:-0}" \
    prospera-app:latest -c '
        set -eu
        for path in app bootstrap config database docs resources routes public tests; do
            rm -rf "/app/$path"
            cp -R "/source/$path" "/app/$path"
        done
        cp /source/phpunit.xml /app/
        rm -f /app/.env /app/public/hot /app/bootstrap/cache/config.php /app/bootstrap/cache/routes*.php
        cd /app
        # Métricas HTTP só na cópia descartável do bootstrap, nunca no arquivo oficial.
        if [ "$E09_METRICS" = 1 ]; then
            sed -i "/withMiddleware(function/a\\        \$middleware->prepend(\\\\Tests\\\\Performance\\\\OctaneMetrics::class);" bootstrap/app.php
            grep -q OctaneMetrics bootstrap/app.php
        fi
        php artisan migrate --force
        php artisan permissions:sync
        php artisan audit:check
        php tests/Performance/e09-fixtures.php
        php artisan organization:check
        exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=1 --max-requests=20000
    ' >/dev/null
docker network connect "$prefix-browser" "$prefix-app"
echo "Laboratório: $prefix, app http://127.0.0.1:$port, senha sintética password"
echo 'Aguarde /up antes de executar os clientes. Não exponha este laboratório fora do loopback.'
