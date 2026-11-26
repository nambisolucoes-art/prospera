#!/bin/bash
# S07: evolução do banco em PostgreSQL 18 descartável, com dados existentes (laboratório prospera-s07-lab).
# 1) Lotações antigas (encerrada, vigente, futura) e exceções existentes.  2) rollback das duas migrations da S07.
# 3) reaplicação.  4) o fuso volta ao padrão e a situação de cada lotação é idêntica antes, no meio e depois.
# 5) o banco recusa estados incompatíveis.
cd "$(dirname "$0")/../.." || exit 1
docker cp tests/Performance/s07-evolution.php prospera-s07-lab-app:/tmp/s07-evolution.php
app() { docker exec -e DB_DATABASE=s07 prospera-s07-lab-app "$@"; }
sql() { docker exec prospera-s07-lab-db psql -U postgres s07 -Atc "$1"; }
cols() { sql "select string_agg(column_name, ',' order by ordinal_position) from information_schema.columns where table_name='$1'"; }

echo "== 1. massa: lotações (encerrada, vigente e futura) em unidades de fusos diferentes"
docker exec -e DB_DATABASE=s07 -e APP_ENV=testing prospera-s07-lab-app php /tmp/s07-evolution.php seed
sql "update establishments set timezone='America/Manaus' where code like '%caetite'"
echo "estabelecimentos por fuso: $(sql "select string_agg(timezone || '=' || n, ' ') from (select timezone, count(*) n from establishments group by 1 order by 1) t")"
echo "exceções existentes: $(sql 'select count(*) from hour_exceptions')"
echo -n "antes   "; app php /tmp/s07-evolution.php status

echo "== 2. rollback das duas migrations da S07"
app php artisan migrate:rollback --step=2 --force 2>&1 | grep -E "DONE|Rolling" | sed 's/^ *//'
echo "coluna timezone existe? $(sql "select count(*) from information_schema.columns where table_name='establishments' and column_name='timezone'")  tabela hour_exceptions existe? $(sql "select count(*) from information_schema.tables where table_name='hour_exceptions'")"
echo "constraint do fuso removida? $(sql "select count(*) from pg_constraint where conname='establishments_timezone_check'")"
echo "estabelecimentos preservados: $(sql 'select count(*) from establishments')  lotações preservadas: $(sql 'select count(*) from employee_assignments')"
echo -n "rollback "; app php /tmp/s07-evolution.php status

echo "== 3. reaplicação"
app php artisan migrate --force 2>&1 | grep -E "DONE|Running" | sed 's/^ *//'
echo "fusos após a reaplicação: $(sql "select string_agg(distinct timezone, ',') from establishments")  (padrão para todas as unidades existentes)"
echo "estabelecimentos: $(sql 'select count(*) from establishments')  hour_exceptions: $(sql 'select count(*) from hour_exceptions')"
echo "constraint do fuso: $(sql "select pg_get_constraintdef(oid) from pg_constraint where conname='establishments_timezone_check'")"
echo -n "depois  "; app php /tmp/s07-evolution.php status

echo "== 4. constraints: o banco recusa estados incompatíveis"
acc=$(sql "select id from clients where code='grupo-gartan'"); co=$(sql "select id from companies where code='gartan-roupas'")
unit=$(sql "select id from establishments where code like '%guanambi'"); other=$(sql "select e.id from establishments e join companies c on c.id=e.company_id where c.code='gartan-express' limit 1")
try() { out=$(docker exec prospera-s07-lab-db psql -U postgres s07 -Atc "$2" 2>&1 | head -1); case "$out" in *violates*) echo "recusado ($1): $(echo "$out" | sed 's/.*violates/violates/' | cut -c1-70)";; INSERT*) echo "ACEITO ($1)  <<< FALHA";; *) echo "?? ($1): $out";; esac; }
ins() { echo "insert into hour_exceptions (account_id,company_id,establishment_id,kind,starts_on,ends_on,shifts,applies_to_overrides,created_at,updated_at) values ($acc,$co,$1,$2,$3,$4,$5,$6,now(),now())"; }
try "tipo desconhecido" "$(ins null "'party'" "'2099-01-01'" "'2099-01-01'" null false)"
try "fim antes do início" "$(ins null "'holiday'" "'2099-01-02'" "'2099-01-01'" null false)"
try "horário especial sem turnos" "$(ins null "'special_hours'" "'2099-01-01'" "'2099-01-01'" null false)"
try "feriado com turnos" "$(ins null "'holiday'" "'2099-01-01'" "'2099-01-01'" "'[]'::json" false)"
try "applies_to_overrides em unidade" "$(ins $unit "'holiday'" "'2099-01-01'" "'2099-01-01'" null true)"
try "unidade de outra empresa" "$(ins $other "'holiday'" "'2099-01-01'" "'2099-01-01'" null false)"
try "conta inexistente" "insert into hour_exceptions (account_id,company_id,kind,starts_on,ends_on,applies_to_overrides,created_at,updated_at) values (999999,$co,'holiday','2099-01-01','2099-01-01',false,now(),now())"
try "fuso vazio" "update establishments set timezone='' where id=$unit"
echo "válida aceita: $(docker exec prospera-s07-lab-db psql -U postgres s07 -Atc "$(ins null "'holiday'" "'2099-01-01'" "'2099-01-01'" null false) returning id" 2>&1 | head -1)"
sql "delete from hour_exceptions where starts_on='2099-01-01'" >/dev/null
echo "== 5. planos de consulta"
sql "analyze hour_exceptions"
