#!/bin/bash
set -euo pipefail

# E08: evolução do esquema de auditoria em PostgreSQL 18 descartável, a partir da versão anterior com histórico.
# Uso: bash tests/Performance/e08-migrations.sh /copia/anterior /copia/e08
before_dir=$(realpath "$1")
after_dir=$(realpath "$2")
fixture_dir=$(realpath "$(dirname "$0")")
image=${E08_IMAGE:-prospera-app:e08-base}
prefix="prospera-e08-migrations-$$"
cleanup() {
    docker rm -f "$prefix-app" "$prefix-db" >/dev/null 2>&1 || true
    docker network rm "$prefix" >/dev/null 2>&1 || true
}
artifacts=$(mktemp -d)
refusal_log="$artifacts/refusal.log"
legacy_backup="$artifacts/legacy.sql"
trap 'rm -rf "$artifacts"; cleanup' EXIT

docker network create --internal --subnet "${E08_SUBNET:?Informe uma faixa isolada e sem sobreposição}" "$prefix" >/dev/null
docker run -d --rm --name "$prefix-db" --network "$prefix" --network-alias pg \
    --tmpfs /var/lib/postgresql -e POSTGRES_PASSWORD=e08-synthetic -e POSTGRES_DB=e08 postgres:18 >/dev/null
for attempt in {1..30}; do
    if docker exec "$prefix-db" pg_isready -h 127.0.0.1 -U postgres -d e08 >/dev/null; then break; fi
    sleep 1
done

docker run -d --rm --name "$prefix-app" --network "$prefix" --entrypoint sh \
    -v "$before_dir:/before:ro" -v "$after_dir:/after:ro" -v "$fixture_dir:/performance:ro" \
    -e APP_ENV=local -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    -e DB_CONNECTION=pgsql -e DB_HOST=pg -e DB_DATABASE=e08 -e DB_USERNAME=postgres -e DB_PASSWORD=e08-synthetic \
    -e CACHE_STORE=file -e MAIL_MAILER=log -e LOG_CHANNEL=stderr "$image" -c 'sleep infinity' >/dev/null

app() { docker exec "$prefix-app" sh -c "cd /app && $1"; }
sql() { docker exec "$prefix-db" psql -XAt -v ON_ERROR_STOP=1 -U postgres -d e08 -c "$1"; }
use() {
    app "for path in app bootstrap config database routes; do rm -rf /app/\$path; cp -R /$1/\$path /app/\$path; done;
        rm -f /app/.env /app/bootstrap/cache/config.php /app/bootstrap/cache/routes*.php"
}
refused() {
    local output
    if output=$(sql "$1" 2>&1); then
        echo "Falha: aceitou $2"
        exit 1
    fi
    if ! grep -q "$3" <<<"$output"; then
        echo "Falha: $2 recusado por outro motivo: $output"
        exit 1
    fi
    echo "Recusado: $2 ($3)"
}

# 1. Versão anterior com histórico real e linhas legadas (uma coberta pelo histórico central, outra não).
use before
app 'php artisan migrate --force -q && php artisan permissions:sync -q && php /performance/e04-fixtures.php'
sql "insert into directory_audit_events (operation_id, actor_id, account_id, event, entity_type, entity_id, changes, created_at)
    select gen_random_uuid(), null, account_id, 'company.created', 'company', entity_id, '{}', created_at
    from audit_records where entity_type = 'company' order by id limit 1"
sql "insert into directory_audit_events (operation_id, actor_id, account_id, event, entity_type, entity_id, changes, created_at)
    values (gen_random_uuid(), null, (select min(id) from clients), 'person.updated', 'person', 999999,
    '{\"after\":{\"name\":\"Somente no legado\",\"document\":\"52998224725\"}}', now() - interval '30 days')"
docker exec "$prefix-db" pg_dump -U postgres -d e08 --data-only \
    -t directory_audit_events -t access_catalog_events > "$legacy_backup"
before=$(sql 'select count(*) from audit_records')
organizational=$(sql 'select count(*) from audit_records where company_id is not null')
echo "Antes: $before eventos, $organizational com empresa"

# 2. Histórico incoerente impede a migration, sem aplicar nada.
use after
sql "insert into audit_records (operation_id, actor_name, account_id, context, entity_type, entity_id, entity_name,
    event, changes, created_at, establishment_id)
    values (gen_random_uuid(), 'Teste', (select min(id) from clients), 'account', 'establishment', 1, 'Incoerente',
    'updated', '{}', now(), (select min(id) from establishments))"
if app 'php artisan migrate --force' >"$refusal_log" 2>&1; then
    echo 'Falha: a migration aceitou histórico incoerente'
    exit 1
fi
grep -o 'contexto organizacional incoerente[^.]*' "$refusal_log" | head -1 || docker exec "$prefix-app" true
[[ $(sql "select count(*) from information_schema.columns where table_name = 'audit_records' and column_name = 'inspection_id'") == 0 ]]
echo 'Recusada a evolução com unidade sem empresa; esquema intacto'
sql "delete from audit_records where entity_name = 'Incoerente'"

# 3. Evolução: colunas, FKs, CHECKs, índices e importação legada sem duplicatas.
app 'php artisan migrate --force && php artisan audit:check && php artisan organization:check'
[[ $(sql 'select count(*) from audit_records') == $(( before + 1 )) ]]
[[ $(sql "select count(*) from audit_records where legacy_key like 'e08:%'") == 1 ]]
[[ $(sql "select count(*) from audit_records where company_id is not null") == "$organizational" ]]
sql "select conname from pg_constraint where conrelid = 'audit_records'::regclass and contype in ('f', 'c') order by 1"
sql "select indexname from pg_indexes where tablename = 'audit_records' order by 1"
echo "Depois: $(sql 'select count(*) from audit_records') eventos, 1 linha legada importada e nenhuma duplicata"

# 4. Proteções no banco.
account=$(sql 'select account_id from companies group by account_id having count(*) > 1 order by account_id limit 1')
other=$(sql "select max(id) from clients")
foreign_company=$(sql "select min(id) from companies where account_id = $other")
unit=$(sql "select min(id) from establishments where account_id = $account")
refused "insert into audit_records (operation_id, actor_name, account_id, context, entity_type, entity_id, entity_name,
    event, changes, created_at, establishment_id) values (gen_random_uuid(), 'T', $account, 'account', 'x', 1, 'T',
    'updated', '{}', now(), $unit)" 'unidade sem empresa' audit_records_establishment_requires_company
refused "insert into audit_records (operation_id, actor_name, account_id, context, entity_type, entity_id, entity_name,
    event, changes, created_at, company_id) values (gen_random_uuid(), 'T', $account, 'account', 'x', 1, 'T',
    'updated', '{}', now(), $foreign_company)" 'empresa de outra conta' audit_records_company_id_account_id_foreign
refused "insert into audit_records (operation_id, actor_name, account_id, context, entity_type, entity_id, entity_name,
    event, changes, created_at, company_id) values (gen_random_uuid(), 'T', null, 'system', 'x', 1, 'T',
    'updated', '{}', now(), $foreign_company)" 'empresa sem conta' audit_records_company_requires_account

company=$(sql "select company_id from establishments where id = $unit")
wrong_company=$(sql "select min(id) from companies where account_id = $account and id <> $company")
refused "insert into audit_records (operation_id, actor_name, account_id, context, entity_type, entity_id, entity_name,
    event, changes, created_at, company_id, establishment_id) values (gen_random_uuid(), 'T', $account, 'account',
    'x', 1, 'T', 'updated', '{}', now(), $wrong_company, $unit)" 'unidade de outra empresa da conta' \
    audit_records_establishment_id_company_id_account_id_foreign

# Unidade referenciada só pelo histórico: a exclusão física é recusada pela FK da auditoria.
orphan=$(sql "insert into establishments (account_id, company_id, code, name, is_headquarters, is_primary, active,
    created_at, updated_at) values ($account, $company, 'e08-historico', 'Unidade com histórico', false, false, false,
    now(), now()) returning id" | head -1)
sql "insert into audit_records (operation_id, actor_name, account_id, context, entity_type, entity_id, entity_name,
    event, changes, created_at, company_id, establishment_id) values (gen_random_uuid(), 'T', $account, 'account',
    'establishment', $orphan, 'Unidade com histórico', 'created', '{}', now(), $company, $orphan)" >/dev/null
refused "delete from establishments where id = $orphan" 'exclusão física de unidade com histórico' \
    audit_records_establishment_id_company_id_account_id_foreign
before=$(( before + 1 ))

# Teste com papel sem propriedade do esquema, mesmo que tenha permissões DML amplas.
sql 'create role e08_runtime; grant select, insert, update, delete, truncate on audit_records to e08_runtime'
refused "set role e08_runtime; update audit_records set actor_name = 'Adulterado'" 'alteração em lote' 'histórico de auditoria'
refused 'set role e08_runtime; delete from audit_records' 'exclusão em lote' 'histórico de auditoria'
refused 'set role e08_runtime; truncate audit_records' 'truncate' 'histórico de auditoria'

# O agrupamento continua possível dentro da mesma transação, inclusive com savepoint.
sql "begin;
    insert into audit_records (operation_id, actor_name, context, entity_type, entity_id, entity_name,
        event, changes, created_at) values ('00000000-0000-4000-8000-000000000005', 'Teste', 'system',
        'test', 1, 'Provisório', 'updated', '{}', now());
    savepoint consolidation;
    update audit_records set entity_name = 'Agrupado'
        where operation_id = '00000000-0000-4000-8000-000000000005';
    delete from audit_records where entity_name = 'Agrupado';
    commit" | grep -q 'DELETE 1'

# 5. A remoção preserva a trilha central. O backup descartável restaura as linhas para ensaiar
# a reversão até a E07 e a reaplicação com os mesmos dados de entrada.
[[ $(sql "select count(*) from information_schema.tables where table_name in ('directory_audit_events', 'access_catalog_events')") == 0 ]]
app 'php artisan migrate:rollback --step=1 --force'
[[ $(sql "select count(*) from directory_audit_events") == 0 ]]
docker exec -i "$prefix-db" psql -X -q -v ON_ERROR_STOP=1 -U postgres -d e08 < "$legacy_backup"
app 'php artisan migrate:rollback --step=3 --force'
[[ $(sql 'select count(*) from audit_records') == "$before" ]]
[[ $(sql "select count(*) from information_schema.columns where table_name = 'audit_records' and column_name in ('inspection_id', 'represented_user_name', 'write_transaction')") == 0 ]]
[[ $(sql "select count(*) from pg_indexes where indexname = 'audit_records_actor_id_created_at_index'") == 1 ]]
echo "Reversão: $(sql 'select count(*) from audit_records') eventos (importação da E08 removida, histórico preservado)"
app 'php artisan migrate --force -q'
[[ $(sql 'select count(*) from audit_records') == $(( before + 1 )) ]]
[[ $(sql "select count(*) from information_schema.tables where table_name in ('directory_audit_events', 'access_catalog_events')") == 0 ]]
echo 'Reaplicação: mesma contagem e mesma importação'

# 6. Instalação limpa da versão da E08.
sql 'drop schema public cascade; create schema public'
app 'php artisan migrate --force -q && php artisan permissions:sync -q && php artisan audit:check'
echo 'Instalação limpa aprovada'

# 7. Custo de inserção: 200 mil eventos sobre 800 mil, com os índices da E08 e com os anteriores.
app 'php /performance/e04-fixtures.php >/dev/null'
docker exec -i "$prefix-db" psql -X -q -U postgres -d e08 -v target=800000 < "$fixture_dir/e08-volume.sql" >/dev/null
insert_cost() {
    docker exec "$prefix-db" psql -XAt -U postgres -d e08 -c '\timing on' -c "
        begin;
        insert into audit_records (operation_id, actor_id, actor_name, account_id, context, entity_type, entity_id,
            entity_name, event, changes, created_at, company_id, establishment_id)
        select gen_random_uuid(), actor_id, actor_name, account_id, context, entity_type, entity_id + 5000000,
            entity_name, event, changes, created_at, company_id, establishment_id
        from audit_records where actor_name like 'Autor sintético %' order by id limit 200000;
        rollback" | grep -i 'time'
    sql 'vacuum analyze audit_records' >/dev/null
}
echo "Inserção de 200 mil com os índices da E08: $(insert_cost)"
app 'php artisan migrate:rollback --step=2 --force -q'
echo "Inserção de 200 mil com os mesmos índices, sem a proteção: $(insert_cost)"
sql 'drop index audit_records_account_id_actor_id_created_at_index;
    drop index audit_records_account_id_entity_type_event_index;
    drop index audit_records_company_id_created_at_id_index;
    drop index audit_records_establishment_id_created_at_id_index;
    create index audit_records_actor_id_created_at_index on audit_records (actor_id, created_at);
    create index audit_records_entity_type_event_index on audit_records (entity_type, event)' >/dev/null
echo "Inserção de 200 mil com os índices anteriores: $(insert_cost)"
