#!/bin/bash
set -euo pipefail

# Uso: bash tests/Performance/run-octane.sh /caminho/da/copia 1
source_dir=$(realpath "$1")
workers=${2:-1}
fixture_dir=$(realpath "$(dirname "$0")")
prefix="prospera-e03-$$"
cleanup() {
    docker rm -f "$prefix-app" "$prefix-db" >/dev/null 2>&1 || true
    docker network rm "$prefix" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create --internal "$prefix" >/dev/null
docker run -d --rm --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD=e03-synthetic -e POSTGRES_DB=e03 \
    postgres:18 >/dev/null
for attempt in {1..30}; do
    if docker exec "$prefix-db" pg_isready -h 127.0.0.1 -U postgres -d e03 >/dev/null; then break; fi
    sleep 1
done

docker run -d --rm --name "$prefix-app" --network "$prefix" --entrypoint sh \
    -v "$source_dir:/source:ro" -v "$fixture_dir:/performance:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e APP_URL=http://127.0.0.1:8000 \
    -e DB_CONNECTION=pgsql -e DB_HOST=pg -e DB_DATABASE=e03 -e DB_USERNAME=postgres -e DB_PASSWORD=e03-synthetic \
    -e E03_CHECK_MIGRATION="${E03_CHECK_MIGRATION:-0}" -e E03_INDEX_PLANS="${E03_INDEX_PLANS:-0}" -e SESSION_DRIVER=database -e CACHE_STORE=file \
    -e BCRYPT_ROUNDS=4 -e LOG_CHANNEL=stderr -e MAIL_MAILER=log \
    prospera-app:latest -c '
        set -eu
        for path in app bootstrap config database docs resources routes; do
            rm -rf "/app/$path"
            cp -R "/source/$path" "/app/$path"
        done
        rm -f /app/.env /app/bootstrap/cache/config.php /app/bootstrap/cache/routes*.php
        mkdir -p /app/tests/Performance
        cp -R /performance/. /app/tests/Performance/
        printf "\nrequire \"/performance/octane-routes.php\";\n" >> routes/web.php
        sed -i "/withMiddleware(function/a\        \$middleware->prepend(\\\\Tests\\\\Performance\\\\OctaneMetrics::class);" bootstrap/app.php
        php artisan migrate --force
        if [ "$E03_CHECK_MIGRATION" = 1 ]; then
            php artisan migrate:rollback --step=1 --force
            php artisan migrate --force
        fi
        php artisan permissions:sync
        php artisan audit:check
        php /performance/octane-fixtures.php
        exec php artisan octane:frankenphp --host=127.0.0.1 --port=8000 --workers="$1"
    ' setup "$workers" >/dev/null

for attempt in {1..45}; do
    if docker exec "$prefix-app" php -r 'exit(@file_get_contents("http://127.0.0.1:8000/up") === false ? 1 : 0);'; then
        break
    fi
    sleep 1
done

if [ "${E03_INDEX_PLANS:-0}" = 1 ]; then
    docker exec -i "$prefix-db" psql -X -U postgres -d e03 < "$fixture_dir/index-plans.sql"
fi

docker exec "$prefix-app" frankenphp version
docker exec "$prefix-app" php -r '
require "/app/vendor/autoload.php";
foreach (["laravel/framework", "laravel/octane"] as $package) {
    echo $package, " ", Composer\InstalledVersions::getPrettyVersion($package), PHP_EOL;
}
'
docker exec "$prefix-db" postgres --version
docker exec "$prefix-app" php /performance/permission-cost.php
docker exec "$prefix-app" php /performance/octane-client.php "$workers" || {
    docker logs --tail 100 "$prefix-app"
    exit 1
}
if [ "$workers" = 1 ]; then docker exec "$prefix-app" php /performance/octane-fixtures.php check-audit; fi
