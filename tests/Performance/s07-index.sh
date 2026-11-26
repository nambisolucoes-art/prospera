#!/bin/bash
# S07: justificativa medida do índice hour_exceptions_owner_period_index (PostgreSQL 18 descartável, laboratório prospera-s07-lab).
# Volume sintético: 600 mil exceções em 3 mil empresas (sessão sem FKs, só neste banco). Compara o plano da consulta do
# resolvedor e da validação de sobreposição com o índice e sem ele.
cd "$(dirname "$0")/../.." || exit 1
sql() { docker exec prospera-s07-lab-db psql -U postgres s07 -P pager=off -Atc "$1"; }
q() { docker exec -i prospera-s07-lab-db psql -U postgres s07 -P pager=off; }

echo "== massa sintética"
q <<'SQL' >/dev/null
set session_replication_role = replica;
insert into hour_exceptions (account_id, company_id, establishment_id, kind, starts_on, ends_on, applies_to_overrides, created_at, updated_at)
select 1, 100000 + (n % 3000), case when n % 4 = 0 then null else 200000 + (n % 40) end, 'holiday',
       date '2026-01-01' + (n % 365), date '2026-01-01' + (n % 365), false, now(), now()
from generate_series(1, 600000) n;
analyze hour_exceptions;
SQL
echo "linhas: $(sql 'select count(*) from hour_exceptions')"
rede=$(sql "select id from companies where code='rede-quarenta'")
ids=$(sql "select string_agg(id::text, ',') from establishments where company_id=$rede")
# Uma empresa realista dentro do volume: a rede de 40 unidades recebe as 84 exceções reais mais algumas centenas.
resolver="select id from hour_exceptions where account_id=1 and company_id=$rede and (establishment_id is null or establishment_id in ($ids)) and starts_on <= '2026-12-11' and ends_on >= '2026-12-09'"
overlap="select exists(select 1 from hour_exceptions where account_id=1 and company_id=$rede and establishment_id is null and starts_on <= '2026-12-31' and ends_on >= '2026-12-01')"
bench() { docker exec -i prospera-s07-lab-db psql -U postgres s07 -P pager=off -At <<SQL | grep -E "Scan|Execution Time|Buffers: shared" | head -6
explain (analyze, buffers, costs off, timing off) $1;
SQL
}
for variant in "com o índice" "sem o índice"; do
  if [ "$variant" = "sem o índice" ]; then sql "drop index hour_exceptions_owner_period_index" >/dev/null; fi
  echo "== $variant"
  echo "-- resolvedor"; bench "$resolver"
  echo "-- sobreposição"; bench "$overlap"
done
sql "create index hour_exceptions_owner_period_index on hour_exceptions (company_id, establishment_id, starts_on)" >/dev/null
sql "delete from hour_exceptions where company_id >= 100000" >/dev/null
sql "analyze hour_exceptions"
echo "limpeza: $(sql 'select count(*) from hour_exceptions') linhas restantes; índice recriado: $(sql "select count(*) from pg_indexes where indexname='hour_exceptions_owner_period_index'")"
