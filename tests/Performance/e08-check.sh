#!/bin/bash
set -euo pipefail

# E08: auditoria em volume, planos, custo de escrita, isolamento e carga prolongada.
# Uso: bash tests/Performance/e08-check.sh /caminho/da/copia [baseline|after]
# E08_VOLUMES define os volumes (padrão: 10000 100000 500000 1000000). Somente bancos descartáveis.
# A concorrência usa uma segunda instância FrankenPHP com dois workers, só neste contêiner descartável.
source_dir=$(realpath "$1")
phase=${2:-after}
volumes=${E08_VOLUMES:-10000 100000 500000 1000000}
image=${E08_IMAGE:-prospera-app:e08-base}
fixture_dir=$(realpath "$(dirname "$0")")
prefix="prospera-e08-check-$$"
cleanup() {
    docker rm -f "$prefix-app" "$prefix-db" >/dev/null 2>&1 || true
    docker network rm "$prefix" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker network create --internal --subnet "${E08_SUBNET:?Informe uma faixa isolada e sem sobreposição}" "$prefix" >/dev/null
docker run -d --rm --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD=e08-synthetic -e POSTGRES_DB=e08 \
    postgres:18 -c shared_buffers=256MB >/dev/null
for attempt in {1..30}; do
    if docker exec "$prefix-db" pg_isready -h 127.0.0.1 -U postgres -d e08 >/dev/null; then break; fi
    sleep 1
done

docker run -d --rm --name "$prefix-app" --network "$prefix" --entrypoint sh \
    -v "$source_dir:/source:ro" -v "$fixture_dir:/performance:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e APP_URL=http://127.0.0.1:8000 \
    -e DB_CONNECTION=pgsql -e DB_HOST=pg -e DB_DATABASE=e08 -e DB_USERNAME=postgres -e DB_PASSWORD=e08-synthetic \
    -e SESSION_DRIVER=database -e CACHE_STORE=file -e MAIL_MAILER=log \
    -e BCRYPT_ROUNDS=4 -e LOG_CHANNEL=stderr \
    "$image" -c '
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
        php /performance/e05-fixtures.php
        php /performance/e08-fixtures.php
        php artisan organization:check
        exec php artisan octane:frankenphp --host=127.0.0.1 --port=8000 --workers=1 --max-requests=20000
    ' >/dev/null

for attempt in {1..90}; do
    if docker exec "$prefix-app" php -r 'exit(@file_get_contents("http://127.0.0.1:8000/up") === false ? 1 : 0);' 2>/dev/null; then
        break
    fi
    sleep 1
done

docker exec "$prefix-app" frankenphp version
docker exec "$prefix-db" postgres --version
echo "fase: $phase"

# Exige conclusão explícita: uma exceção renderizada pelo bootstrap não basta como sucesso.
client() {
    local output
    output=$(docker exec -e E08_PHASE="$phase" \
        -e HARNESS_URL="${E08_CLIENT_URL:-http://127.0.0.1:8000}" \
        -e E08_ISOLATION_WORKERS="${E08_CLIENT_WORKERS:-1}" \
        "$prefix-app" php /performance/e08-client.php "$@")
    echo "$output"
    grep -q '"e08":"verificado"' <<<"$output"
}

scenario() {
    docker exec "$prefix-app" php -r '
        $data = json_decode(file_get_contents("/tmp/prospera-e08-scenarios.json"), true);
        echo $data[$argv[1]] ?? $data["scenarios"][$argv[1]]["user"];
    ' "$1"
}
sql() { docker exec "$prefix-db" psql -XAt -v ON_ERROR_STOP=1 -U postgres -d e08 -c "$1"; }

account=$(scenario account)
company=$(scenario establishment_company)
unit=$(scenario establishment)
user30=$(scenario atribuicoes-30)
units30=$(sql "select string_agg(ra.establishment_id::text, ',') from role_assignments ra
    join account_memberships m on m.id = ra.membership_id where m.user_id = $user30")

for volume in $volumes; do
    docker exec -i "$prefix-db" psql -X -q -U postgres -d e08 -v target="$volume" < "$fixture_dir/e08-volume.sql"
    client measure "$volume"
    echo "== planos em $volume eventos"
    docker exec -i "$prefix-db" psql -X -U postgres -d e08 -v account="$account" -v company="$company" \
        -v unit="$unit" -v units30="$units30" < "$fixture_dir/e08-plans.sql"
done

client write
client isolation

# Concorrência: segunda instância com dois workers, só neste ambiente, e dez alterações simultâneas da mesma Pessoa.
docker exec -d -e OCTANE_STATE_FILE=/tmp/octane-race-state.json "$prefix-app" \
    php artisan octane:frankenphp --host=127.0.0.1 --port=8001 --admin-port=2020 --workers=2 --max-requests=20000
race_ready=0
for attempt in {1..60}; do
    if docker exec "$prefix-app" php -r 'exit(@file_get_contents("http://127.0.0.1:8001/up") === false ? 1 : 0);' 2>/dev/null; then
        race_ready=1
        break
    fi
    sleep 1
done
[[ $race_ready == 1 ]] || { echo 'Falha: a instância de concorrência não subiu'; exit 1; }
docker exec -e HARNESS_URL=http://127.0.0.1:8001 "$prefix-app" php /performance/e08-client.php race-setup
docker exec "$prefix-app" test -s /tmp/prospera-e08-race.json
pids=()
for process in 1 2 3 4 5 6 7 8 9 10; do
    docker exec -e HARNESS_URL=http://127.0.0.1:8001 "$prefix-app" php /performance/e08-client.php race "$process" &
    pids+=($!)
done
for pid in "${pids[@]}"; do wait "$pid"; done
race_result=$(docker exec "$prefix-app" php /performance/e08-client.php race-check)
echo "$race_result"
grep -q corrida-verificada <<<"$race_result"

# Repete a alternância de autores, contas e inspeção nos dois workers reais.
E08_CLIENT_URL=http://127.0.0.1:8001 E08_CLIENT_WORKERS=2 client isolation

# E08_ENDURANCE=0 omite a carga prolongada (8 lotes de 450 requisições sequenciais).
if [ "${E08_ENDURANCE:-1}" = 1 ]; then
    for batch in 1 2 3 4 5 6 7 8; do
        client endurance "$batch"
        docker stats --no-stream --format '{"e08":"memoria-conteiner","lote":'"$batch"',"uso":"{{.MemUsage}}"}' \
            "$prefix-app"
    done
fi

docker logs "$prefix-app" 2>&1 | grep -c -i 'worker.*restart\|panic' || true
