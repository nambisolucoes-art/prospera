#!/bin/bash
set -euo pipefail

# Uso: bash tests/Performance/e04-check.sh /caminho/da/copia 1|2
source_dir=$(realpath "$1")
workers=${2:-1}
contextual=${E05_CONTEXTUAL:-0}
fixture_dir=$(realpath "$(dirname "$0")")
prefix="prospera-e04-check-$$"
cleanup() {
    docker rm -f "$prefix-app" "$prefix-db" >/dev/null 2>&1 || true
    docker network rm "$prefix" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create --internal "$prefix" >/dev/null
docker run -d --rm --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD=e04-synthetic -e POSTGRES_DB=e04 \
    postgres:18 >/dev/null
for attempt in {1..30}; do
    if docker exec "$prefix-db" pg_isready -h 127.0.0.1 -U postgres -d e04 >/dev/null; then break; fi
    sleep 1
done

docker run -d --rm --name "$prefix-app" --network "$prefix" --entrypoint sh \
    -v "$source_dir:/source:ro" -v "$fixture_dir:/performance:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e APP_URL=http://127.0.0.1:8000 \
    -e DB_CONNECTION=pgsql -e DB_HOST=pg -e DB_DATABASE=e04 -e DB_USERNAME=postgres -e DB_PASSWORD=e04-synthetic \
    -e SESSION_DRIVER=database -e CACHE_STORE=file \
    -e BCRYPT_ROUNDS=4 -e LOG_CHANNEL=stderr \
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
        php artisan permissions:sync
        php artisan audit:check
        php /performance/e04-fixtures.php
        if [ "$2" = 1 ]; then
            php /performance/e05-fixtures.php
        fi
        exec php artisan octane:frankenphp --host=127.0.0.1 --port=8000 --workers="$1" --max-requests=1000
    ' setup "$workers" "$contextual" >/dev/null

for attempt in {1..45}; do
    if docker exec "$prefix-app" php -r 'exit(@file_get_contents("http://127.0.0.1:8000/up") === false ? 1 : 0);'; then
        break
    fi
    sleep 1
done

docker exec "$prefix-app" frankenphp version
docker exec "$prefix-app" php -r '
require "/app/vendor/autoload.php";
foreach (["laravel/framework", "laravel/octane"] as $package) {
    echo $package, " ", Composer\InstalledVersions::getPrettyVersion($package), PHP_EOL;
}
'
docker exec "$prefix-db" postgres --version
docker exec "$prefix-app" php artisan organization:check
docker exec -e E04_ISOLATION="$(( workers == 1 ))" "$prefix-app" php /performance/e04-client.php

if [ "$contextual" = 1 ]; then
    docker exec -e E05_ISOLATION="$(( workers == 1 ))" "$prefix-app" php /performance/e05-client.php
fi

docker exec -i "$prefix-db" psql -X -v ON_ERROR_STOP=1 -U postgres -d e04 < "$fixture_dir/e04-index-plans.sql"

if [ "$workers" = 2 ]; then
    sql() { docker exec "$prefix-db" psql -XAt -v ON_ERROR_STOP=1 -U postgres -d e04 -c "$1"; }

    race() {
        local table=$1 first_operation=$2 first_unit=$3 second_operation=$4 second_unit=$5
        local lock first second attempt blocked=0
        docker exec "$prefix-db" psql -Xq -v ON_ERROR_STOP=1 -U postgres -d e04 \
            -c "begin; select id from $table where id=2 for update; select pg_sleep(3); commit;" &
        lock=$!
        for attempt in {1..50}; do
            [[ $(sql "select count(*) from pg_stat_activity where wait_event='PgSleep'") == 1 ]] && break
            sleep .05
        done
        docker exec "$prefix-app" php /performance/e04-client.php "$first_operation" "$first_unit" &
        first=$!
        docker exec "$prefix-app" php /performance/e04-client.php "$second_operation" "$second_unit" &
        second=$!
        for attempt in {1..20}; do
            blocked=$(sql "select count(*) from pg_stat_activity where wait_event_type='Lock' and query like '%$table%for update%'")
            [[ $blocked == 2 ]] && break
            sleep .05
        done
        wait "$lock"

        first_status=0
        second_status=0
        wait "$first" || first_status=$?
        wait "$second" || second_status=$?
        echo "Requisições concorrentes esperando pela trava em $table: $blocked"
        [[ $blocked == 2 ]]
    }

    mapfile -t units < <(sql 'select id from establishments where company_id=2 and not is_primary order by id')
    race companies promote "${units[0]}" promote "${units[1]}"
    [[ $first_status == 0 && $second_status == 0 ]]
    [[ $(sql 'select count(*) from establishments where company_id=2 and is_primary and active') == 1 ]]
    [[ $(sql "select count(distinct operation_id) from audit_records where company_id=2 and entity_type='establishment' and event='updated'") == 2 ]]

    candidate=$(sql 'select id from establishments where company_id=2 and not is_primary order by id limit 1')
    race companies promote "$candidate" deactivate "$candidate"
    [[ "$first_status,$second_status" == 0,42 || "$first_status,$second_status" == 42,0 ]]
    [[ $(sql 'select count(*) from establishments where company_id=2 and is_primary and active') == 1 ]]

    race clients create 0 create 0
    [[ "$first_status,$second_status" == 0,42 || "$first_status,$second_status" == 42,0 ]]
    [[ $(sql "select count(*) from companies c join establishments e on e.company_id=c.id where c.account_id=2 and c.tax_root='12ABC345' and e.is_primary and e.active") == 1 ]]
    docker exec "$prefix-app" php artisan organization:check

    if [ "$contextual" = 1 ]; then
        bash "$fixture_dir/e05-races.sh" "$prefix"
    fi
fi
