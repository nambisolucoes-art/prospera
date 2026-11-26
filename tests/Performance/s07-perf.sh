#!/bin/bash
# S07: desempenho do resolvedor de horários e planos de consulta em PostgreSQL 18 (laboratório prospera-s07-lab).
cd "$(dirname "$0")/../.." || exit 1
docker cp tests/Performance/s07-perf.php prospera-s07-lab-app:/tmp/s07-perf.php
app() { docker exec -e DB_DATABASE=s07 prospera-s07-lab-app "$@"; }
sql() { docker exec prospera-s07-lab-db psql -U postgres s07 -c "$1"; }

[ "${1:-}" = prepare ] && app php /tmp/s07-perf.php prepare
echo "== medições"
app php /tmp/s07-perf.php measure
echo "== planos (EXPLAIN ANALYZE) com a massa da rede de 40 unidades"
sql "analyze hour_exceptions; analyze profile_sections; analyze establishments"
rede=$(docker exec prospera-s07-lab-db psql -U postgres s07 -Atc "select id from companies where code='rede-quarenta'")
acc=$(docker exec prospera-s07-lab-db psql -U postgres s07 -Atc "select account_id from companies where id=$rede")
ids=$(docker exec prospera-s07-lab-db psql -U postgres s07 -Atc "select string_agg(id::text, ',') from establishments where company_id=$rede")
echo "-- exceções da empresa e das 40 unidades que cobrem 09/12 a 11/12 (consulta do resolvedor)"
sql "explain (analyze, buffers, costs off, timing off) select id, establishment_id, kind from hour_exceptions where account_id=$acc and company_id=$rede and (establishment_id is null or establishment_id in ($ids)) and starts_on <= '2026-12-11' and ends_on >= '2026-12-09'"
echo "-- sobreposição por dono (validação do cadastro)"
sql "explain (analyze, buffers, costs off, timing off) select exists(select 1 from hour_exceptions where account_id=$acc and company_id=$rede and establishment_id is null and starts_on <= '2026-12-31' and ends_on >= '2026-12-01')"
echo "-- lista do dono na tela"
sql "explain (analyze, buffers, costs off, timing off) select * from hour_exceptions where account_id=$acc and company_id=$rede and establishment_id is null and ends_on >= '2026-12-01' order by starts_on, id limit 200"
echo "-- unidades da empresa para o resolvedor"
sql "explain (analyze, buffers, costs off, timing off) select e.id from establishments e join companies c on c.id=e.company_id and c.account_id=e.account_id where e.account_id=$acc and e.company_id=$rede order by e.id"
echo "-- lotações vigentes por fuso (predicado da S05, com junção por subconsulta)"
sql "explain (analyze, buffers, costs off, timing off) select 1 from employee_assignments a where a.account_id=$acc and ((a.establishment_id in (select id from establishments where timezone='America/Sao_Paulo') and a.starts_on <= '2026-11-26' and (a.ends_on is null or a.ends_on >= '2026-11-26')) or (a.establishment_id in (select id from establishments where timezone='America/Manaus') and a.starts_on <= '2026-11-25' and (a.ends_on is null or a.ends_on >= '2026-11-25')))"
