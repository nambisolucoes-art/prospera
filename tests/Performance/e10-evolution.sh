#!/bin/bash
set -euo pipefail

# Evolução do esquema pré-E09 até o atual, num PostgreSQL 18 descartável.
# Uso: bash tests/Performance/e10-evolution.sh /caminho/arvore-pre-e09 [saida]
# A árvore anterior é um `git archive 48e2191` com os seeders atuais copiados (só a linha enter() removida).
old_tree=$(realpath "${1:?Informe a árvore pré-E09}")
out=${2:-/tmp/prospera-e10/evidence/migrations}
new_tree=$(realpath "$(dirname "$0")/../..")
prefix=prospera-e10-lab-evolucao
mkdir -p "$out"

cleanup() {
    for kind in app db; do
        if [ "$(docker inspect -f '{{ index .Config.Labels "prospera.lab" }}' "$prefix-$kind" 2>/dev/null || true)" = e10 ]; then
            docker rm -f "$prefix-$kind" >/dev/null
        fi
    done
    docker network rm "$prefix" >/dev/null 2>&1 || true
}
trap cleanup EXIT
cleanup

docker network create --internal --label prospera.lab=e10 "$prefix" >/dev/null
docker run -d --rm --label prospera.lab=e10 --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD=synthetic-e10 -e POSTGRES_DB=e10evo postgres:18 >/dev/null
until docker exec "$prefix-db" pg_isready -U postgres -d e10evo >/dev/null 2>&1; do sleep 1; done
sleep 2

docker run -d --rm --label prospera.lab=e10 --name "$prefix-app" --network "$prefix" --entrypoint sleep \
    -v "$old_tree:/old:ro" -v "$new_tree:/new:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e DB_CONNECTION=pgsql -e DB_HOST=pg \
    -e DB_DATABASE=e10evo -e DB_USERNAME=postgres -e DB_PASSWORD=synthetic-e10 -e CACHE_STORE=array \
    -e MAIL_MAILER=array -e BCRYPT_ROUNDS=4 -e LOG_CHANNEL=stderr -e PROSPERA_MULTIPLE_ACCOUNTS=true \
    prospera-app:latest infinity >/dev/null

use_tree() {
    docker exec "$prefix-app" sh -c "
        for path in app bootstrap config database routes tests resources; do rm -rf /app/\$path; cp -R /$1/\$path /app/\$path; done
        rm -f /app/.env /app/bootstrap/cache/*.php"
}

# 1. Esquema e massa pelo código anterior à E09.
use_tree old
docker exec "$prefix-app" php artisan migrate --force > "$out/evolucao-1-migrate-pre-e09.log" 2>&1
docker exec "$prefix-app" php artisan permissions:sync >> "$out/evolucao-1-migrate-pre-e09.log" 2>&1
docker exec "$prefix-app" php artisan db:seed --force > "$out/evolucao-1-seed.log" 2>&1
docker cp "$new_tree/tests/Performance/e10" "$prefix-app:/app/tests/Performance/" >/dev/null
docker exec "$prefix-app" php tests/Performance/e10/evolution-extra.php > "$out/evolucao-1-massa.json" 2>&1
docker exec "$prefix-app" php tests/Performance/e10/evolution-snapshot.php > "$out/evolucao-2-antes.json"
docker cp "$out/evolucao-2-antes.json" "$prefix-app:/tmp/e10-evolution-before.json"

# 2. Backup verificável do esquema anterior.
docker exec "$prefix-db" pg_dump -U postgres -Fc e10evo -f /tmp/pre-e09.dump
docker exec "$prefix-db" pg_restore -l /tmp/pre-e09.dump | grep -c 'TABLE DATA' > "$out/evolucao-2-backup-tabelas.txt"

# 3. Código atual: aplica as migrations pendentes e os comandos de integridade.
use_tree new
docker cp "$new_tree/tests/Performance/e10" "$prefix-app:/app/tests/Performance/" >/dev/null
{
    docker exec "$prefix-app" php artisan migrate:status | grep -E 'Pending|2026_11_23'
    docker exec "$prefix-app" php artisan migrate --force
    docker exec "$prefix-app" php artisan permissions:sync
    docker exec "$prefix-app" php artisan audit:check
    docker exec "$prefix-app" php artisan organization:check
} > "$out/evolucao-3-migrate-atual.log" 2>&1
docker exec "$prefix-app" php tests/Performance/e10/evolution-snapshot.php > "$out/evolucao-3-depois.json"

# 4. Reversão com vínculos únicos: recria users.account_id; depois reaplica.
docker exec "$prefix-app" php artisan migrate:rollback --step=1 --force > "$out/evolucao-4-rollback.log" 2>&1
docker exec "$prefix-app" php tests/Performance/e10/evolution-snapshot.php > "$out/evolucao-4-revertido.json"
docker exec "$prefix-app" php artisan migrate --force >> "$out/evolucao-4-rollback.log" 2>&1
docker exec "$prefix-app" php tests/Performance/e10/evolution-snapshot.php > "$out/evolucao-4-reaplicado.json"

# 5. Identidade em duas contas: a reversão precisa abortar sem alterar o esquema.
docker exec "$prefix-app" php tests/Performance/e10/evolution-multi.php > "$out/evolucao-5-multi.log" 2>&1
set +e
docker exec "$prefix-app" php artisan migrate:rollback --step=1 --force >> "$out/evolucao-5-multi.log" 2>&1
echo "exit=$?" >> "$out/evolucao-5-multi.log"
set -e
docker exec "$prefix-app" php tests/Performance/e10/evolution-snapshot.php > "$out/evolucao-5-depois-abortar.json"

# 6. Restauração do backup anterior em outro banco e evolução pelo código atual.
docker exec "$prefix-db" createdb -U postgres e10_restore
docker exec "$prefix-db" pg_restore -U postgres -d e10_restore /tmp/pre-e09.dump
docker exec -e DB_DATABASE=e10_restore "$prefix-app" php artisan migrate --force > "$out/evolucao-6-restaurado.log" 2>&1
docker exec -e DB_DATABASE=e10_restore "$prefix-app" php tests/Performance/e10/evolution-snapshot.php > "$out/evolucao-6-restaurado.json"

echo "evolucao=concluida $out"
