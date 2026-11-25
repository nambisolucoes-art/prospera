#!/bin/bash
# S05: concorrência real de lotações em PostgreSQL descartável (laboratório s04-lab-app e s04-lab-db, com fixtures).
# Quatro processos disputam a mesma pessoa. Esperado: A e B gravam uma só lotação, C grava as quatro, D é recusado pelo banco.
cd "$(dirname "$0")/../.." || exit 1
docker cp tests/Performance/s05-race.php s04-lab-app:/tmp/s05-race.php
run() { docker exec s04-lab-app php /tmp/s05-race.php "$@"; }
sql() { docker exec s04-lab-db psql -U postgres s04 -Atc "$1"; }
sql "truncate employee_assignments"
echo "== A: 4 processos, mesma unidade, períodos sobrepostos"
for s in 2026-01-01 2026-03-01 2026-05-01 2026-07-01; do run A gartan-roupas-guanambi $s null 0 & done; wait
sql "select count(*) from employee_assignments"
echo "== B: 4 processos, unidades diferentes, todas principais"
sql "truncate employee_assignments"
for u in gartan-roupas-guanambi gartan-roupas-caetite gartan-roupas-brumado gartan-roupas; do run B $u 2026-01-01 null 1 & done; wait
sql "select count(*), count(*) filter (where is_primary) from employee_assignments"
echo "== C: 4 processos, mesma unidade, períodos adjacentes que não se sobrepõem"
sql "truncate employee_assignments"
i=0; for p in "2026-01-01 2026-01-31" "2026-02-01 2026-02-28" "2026-03-01 2026-03-31" "2026-04-01 2026-04-30"; do set -- $p; run C gartan-roupas-guanambi $1 $2 0 & done; wait
sql "select count(*) from employee_assignments"
echo "== D: integridade no banco (SQL direto)"
sql "insert into employee_assignments(account_id,company_id,employee_id,establishment_id,starts_on,ends_on,is_primary,created_at,updated_at) select a.id, c.id, e.id, (select id from establishments where company_id <> c.id limit 1), '2026-01-01', null, false, now(), now() from clients a join companies c on c.account_id=a.id join employees e on e.company_id=c.id limit 1" 2>&1 | head -2
sql "insert into employee_assignments(account_id,company_id,employee_id,establishment_id,starts_on,ends_on,is_primary,created_at,updated_at) select a.id, c.id, e.id, (select id from establishments where company_id = c.id limit 1), '2026-02-01', '2026-01-01', false, now(), now() from clients a join companies c on c.account_id=a.id join employees e on e.company_id=c.id limit 1" 2>&1 | head -2
sql "select count(*) from employee_assignments"
