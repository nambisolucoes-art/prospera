#!/bin/bash
# S05 (contatos): concorrência em PostgreSQL descartável. Esperado: edições simultâneas de contato terminam coerentes
# (um valor, modo override) e transferências simultâneas da principal deixam exatamente uma principal.
cd "$(dirname "$0")/../.." || exit 1
docker cp tests/Performance/s05-race-contacts.php s04-lab-app:/tmp/s05-race-contacts.php
run() { docker exec s04-lab-app php /tmp/s05-race-contacts.php "$@"; }
sql() { docker exec s04-lab-db psql -U postgres s04 -Atc "$1"; }
sql "truncate employee_assignments"
sql "insert into employee_assignments(account_id,company_id,employee_id,establishment_id,starts_on,ends_on,is_primary,created_at,updated_at)
     select 1, c.id, e.id, s.id, '2026-01-01', null, (s.code like '%guanambi'), now(), now()
     from companies c join employees e on e.company_id=c.id join people p on p.id=e.person_id and p.name='Carla Dias'
     join establishments s on s.company_id=c.id and s.code in ('gartan-roupas-guanambi','gartan-roupas-caetite')"
ids=($(sql "select id from employee_assignments order by id"))
echo "== A: 4 edições simultâneas de contato na mesma lotação"
for n in 1 2 3 4; do run contacts "${ids[0]}" "p$n@roupas.test" & done; wait
sql "select contact_mode, work_contacts from employee_assignments where id=${ids[0]}"
echo "== B: 4 transferências simultâneas da principal entre duas lotações"
for n in 1 2; do run primary "${ids[0]}" & run primary "${ids[1]}" & done; wait
sql "select count(*) filter (where is_primary) from employee_assignments"
