#!/bin/bash
# S07: concorrência real em PostgreSQL 18 descartável (laboratório prospera-s07-lab). Cada cliente é um processo PHP próprio.
# A: 6 exceções simultâneas do mesmo dono e período. Esperado: exatamente 1 gravada e 5 recusadas por sobreposição.
# B: 6 exceções simultâneas de períodos diferentes. Esperado: todas gravadas, sem sobreposição.
# C: exceção corporativa com resumo antigo enquanto a unidade muda a herança. Esperado: 422 ou gravação coerente, nunca resumo velho aplicado.
# D: edições simultâneas do horário semanal da empresa e das unidades. Esperado: sem deadlock, valor final igual ao último evento auditado.
# E: troca de fuso simultânea com salvamento de horário e de exceção. Esperado: sem deadlock e fuso final igual ao último evento.
# F: auditoria consistente: um evento por escrita efetiva e nenhuma escrita parcial.
cd "$(dirname "$0")/../.." || exit 1
docker cp tests/Performance/s07-race.php prospera-s07-lab-app:/tmp/s07-race.php
run() { docker exec -e DB_DATABASE=s07 prospera-s07-lab-app php /tmp/s07-race.php "$@"; }
sql() { docker exec prospera-s07-lab-db psql -U postgres s07 -Atc "$1"; }
summary() { sort | uniq -c | sort -rn; }

# A auditoria é imutável (gatilho do banco): nada dela é apagado. As verificações usam só eventos posteriores a este ponto.
clean() { sql "truncate hour_exceptions"; sql "delete from profile_sections where section='hours_weekly'" >/dev/null; }
clean
mark=$(sql "select coalesce(max(id),0) from audit_records")

echo "== A: 6 exceções simultâneas, mesmo dono e período"
for n in 1 2 3 4 5 6; do run exception empresa 2099-01-10 2099-01-12 & done | summary; wait
sql "select count(*) from hour_exceptions where establishment_id is null"

echo "== B: 6 exceções de períodos diferentes, mesmo dono"
sql "truncate hour_exceptions"
for n in 1 2 3 4 5 6; do run exception empresa 2099-02-0$n 2099-02-0$n & done | summary; wait
sql "select count(*), (select count(*) from hour_exceptions a join hour_exceptions b on a.id < b.id and a.company_id=b.company_id and a.establishment_id is not distinct from b.establishment_id and a.starts_on <= b.ends_on and b.starts_on <= a.ends_on) as sobrepostas from hour_exceptions"

echo "== B2: períodos cruzados em rajadas (30 tentativas, janelas de 3 dias deslizantes)"
sql "truncate hour_exceptions"
for n in $(seq 1 30); do d=$(printf '%02d' $(( (n % 15) + 1 ))); e=$(printf '%02d' $(( (n % 15) + 3 ))); run exception empresa 2099-03-$d 2099-03-$e & done | summary; wait
sql "select count(*) as gravadas, (select count(*) from hour_exceptions a join hour_exceptions b on a.id < b.id and a.company_id=b.company_id and a.establishment_id is not distinct from b.establishment_id and a.starts_on <= b.ends_on and b.starts_on <= a.ends_on) as sobrepostas from hour_exceptions"

echo "== C: resumo antigo da exceção corporativa x mudança de herança da unidade"
clean
run hours-save empresa 08:00 >/dev/null
run hours-save guanambi 09:00 >/dev/null
for round in 1 2 3 4 5 6; do
  sql "truncate hour_exceptions"
  run hours-save guanambi 09:00 >/dev/null
  ( run stale-exception 2099-04-0$round 2099-04-0$round 400 & run hours-mode guanambi inherit & wait ) | summary
  sql "select 'rodada $round: exceções=' || count(*) || ' applies=' || coalesce(bool_or(applies_to_overrides)::text,'-') || ' modo da unidade=' || (select coalesce(string_agg(mode,','),'sem linha') from profile_sections where section='hours_weekly' and establishment_id is not null) from hour_exceptions"
done

echo "== D: salvamentos simultâneos do horário (empresa e unidades)"
clean
for n in 1 2 3 4 5 6 7 8; do run hours-save empresa 0$n:00 & run hours-save guanambi 1$((n % 7)):00 & run hours-save caetite 0$n:30 & done | summary; wait
sql "select count(*) from profile_sections where section='hours_weekly'"
sql "select (value->'days'->'mon'->'shifts'->0->>'start') = (select changes->'value'->'after'->'days'->'mon'->'shifts'->0->>'start' from audit_records where entity_type='profile-section' and entity_id=p.id and event in ('created','updated') order by id desc limit 1) from profile_sections p where section='hours_weekly' order by id"

echo "== E: troca de fuso x horário x exceção da mesma unidade"
sql "truncate hour_exceptions"
for n in 1 2 3 4 5 6; do
  zone=$([ $((n % 2)) = 0 ] && echo America/Manaus || echo America/New_York)
  run timezone guanambi $zone & run hours-save guanambi 0$n:00 & run exception guanambi 2099-05-0$n 2099-05-0$n & done | summary; wait
sql "select (select timezone from establishments where code like '%guanambi') = (select changes->'timezone'->>'after' from audit_records where entity_type='establishment' and event='updated' and changes->'timezone' is not null and id > $mark order by id desc limit 1)"
sql "select count(*) from hour_exceptions where establishment_id is not null"

echo "== F: consistência da auditoria das exceções"
sql "select count(*) filter (where event='created') as criacoes, count(*) as eventos, (select count(*) from hour_exceptions) as linhas from audit_records where entity_type='hour-exception' and id > $mark"
sql "select count(*) from hour_exceptions e where not exists (select 1 from audit_records a where a.entity_type='hour-exception' and a.entity_id=e.id and a.event='created')"

echo "== G: deadlocks registrados pelo PostgreSQL durante os cenários"
docker logs prospera-s07-lab-db 2>&1 | grep -ci 'deadlock detected' || true
