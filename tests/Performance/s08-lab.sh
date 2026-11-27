#!/bin/bash
set -euo pipefail

# Laboratório descartável da S08: PostgreSQL 18 em tmpfs e FrankenPHP numa rede interna, a partir da imagem de produção.
# Nunca usa docker compose, volumes ou containers oficiais. Só remove recursos com o rótulo s08.
# Uso: LAB_PASSWORD=... S08_WORKERS=2 tests/Performance/s08-lab.sh up|down   (assets: rode `npm run build` antes)
source_dir=$(realpath "$(dirname "$0")/../..")
prefix=${S08_PREFIX:-prospera-s08-lab}
port=${S08_PORT:-18207}
workers=${S08_WORKERS:-1}
action=${1:-up}
[[ "$prefix" == prospera-s08-lab* ]] || { echo 'Prefixo de laboratório inválido'; exit 1; }

if [ "$action" = down ]; then
    for kind in app db; do
        name="$prefix-$kind"
        if [ "$(docker inspect -f '{{ index .Config.Labels "prospera.lab" }}' "$name" 2>/dev/null || true)" = s08 ]; then
            docker rm -f "$name" >/dev/null
        fi
    done
    docker network rm "$prefix" "$prefix-browser" >/dev/null 2>&1 || true
    exit
fi

: "${LAB_PASSWORD:?Defina LAB_PASSWORD (senha sintética dos usuários do laboratório)}"

docker network create --internal --label prospera.lab=s08 "$prefix" >/dev/null
docker network create --label prospera.lab=s08 "$prefix-browser" >/dev/null
docker run -d --rm --label prospera.lab=s08 --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD="$LAB_PASSWORD" -e POSTGRES_DB=s08 postgres:18 >/dev/null

for attempt in {1..30}; do
    if docker exec "$prefix-db" pg_isready -U postgres -d s08 >/dev/null 2>&1; then break; fi
    sleep 1
done

docker run -d --rm --label prospera.lab=s08 --name "$prefix-app" --network "$prefix" \
    -p "127.0.0.1:$port:8000" --entrypoint sh -v "$source_dir:/source:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    -e APP_URL="http://127.0.0.1:$port" -e DB_CONNECTION=pgsql -e DB_HOST=pg -e DB_DATABASE=s08 \
    -e DB_USERNAME=postgres -e DB_PASSWORD="$LAB_PASSWORD" -e SESSION_DRIVER=database -e CACHE_STORE=file \
    -e MAIL_MAILER=log -e BCRYPT_ROUNDS=4 -e LOG_CHANNEL=stderr -e LAB_PASSWORD="$LAB_PASSWORD" -e S08_WORKERS="$workers" \
    prospera-app:latest -c '
        set -eu
        for path in app bootstrap config database docker docs resources routes public tests; do
            rm -rf "/app/$path"
            cp -R "/source/$path" "/app/$path"
        done
        cp /source/phpunit.xml /app/
        rm -f /app/.env /app/public/hot /app/bootstrap/cache/config.php /app/bootstrap/cache/routes*.php
        cd /app
        php artisan migrate --force
        php artisan permissions:sync
        php artisan audit:check
        php tests/Performance/s08-fixtures.php
        php artisan organization:check
        exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers="$S08_WORKERS" --max-requests=20000
    ' >/dev/null

# A rede interna não publica portas: o app também entra na rede de navegador, só para o loopback do host.
docker network connect "$prefix-browser" "$prefix-app"

echo "Laboratório: $prefix, app http://127.0.0.1:$port (rede interna $prefix, banco $prefix-db)"
echo 'Aguarde /up. Não exponha este laboratório fora do loopback.'
