#!/bin/bash
# S06: concorrência real do perfil institucional em PostgreSQL descartável (laboratório s04-lab-app e s04-lab-db).
# A: 4 criações simultâneas da mesma seção. Esperado: 1 linha, sem violar o índice único.
# B: 4 publicações simultâneas da mesma seção. Esperado: 1 evento publicado e o estado publicado coerente.
# C: salvar e publicar ao mesmo tempo. Esperado: o publicado é sempre um dos rascunhos já gravados.
# D: unidades diferentes gravando a mesma seção: nenhuma interfere na outra.
# E: salvamentos simultâneos: a cadeia de auditoria (antes e depois) é contínua e o valor final é o do último evento.
# F: salvar e publicar em paralelo: o publicado é sempre um rascunho que foi auditado.
cd "$(dirname "$0")/../.." || exit 1
docker cp tests/Performance/s06-race.php s04-lab-app:/tmp/s06-race.php
run() { docker exec s04-lab-app php /tmp/s06-race.php "$@"; }
sql() { docker exec s04-lab-db psql -U postgres s04 -Atc "$1"; }

sql "truncate profile_sections"
echo "== A: 4 criações simultâneas da seção about da empresa"
for n in 1 2 3 4; do run save empresa about "T$n" & done; wait
sql "select count(*) from profile_sections where section='about' and establishment_id is null"

echo "== B: 4 publicações simultâneas"
before=$(sql "select count(*) from audit_records where event='published'")
for n in 1 2 3 4; do run publish empresa about & done; wait
after=$(sql "select count(*) from audit_records where event='published'")
echo "eventos publicados: $((after - before))"
sql "select published_mode, (published_value->>'title') = (value->>'title') from profile_sections where section='about'"

echo "== C: salvar e publicar ao mesmo tempo"
for n in 1 2 3 4; do run save empresa about "V$n" & run publish empresa about & done; wait
sql "select (published_value->>'title') in ('T1','T2','T3','T4','V1','V2','V3','V4') from profile_sections where section='about'"

echo "== D: unidades diferentes, mesma seção"
for u in gartan-roupas-guanambi gartan-roupas-caetite gartan-roupas; do run save $u about "U-$u" & done; wait
sql "select count(*), count(distinct establishment_id) from profile_sections where section='about' and establishment_id is not null"

echo "== E: 8 salvamentos simultâneos: a cadeia de auditoria precisa ser contínua (antes de cada evento = depois do anterior)"
sql "truncate profile_sections"
run save empresa about "base"
for n in 1 2 3 4 5 6 7 8; do run save empresa about "E$n" & done; wait
sql "select count(*) filter (where broken) from (select (changes->'value'->'before'->>'title') is distinct from lag(changes->'value'->'after'->>'title') over (order by id) and lag(id) over (order by id) is not null as broken from audit_records where entity_type='profile-section' and event in ('created','updated') and entity_id=(select id from profile_sections where section='about' and establishment_id is null)) t"
sql "select (value->>'title') = (select changes->'value'->'after'->>'title' from audit_records where entity_type='profile-section' and event in ('created','updated') order by id desc limit 1) from profile_sections where section='about'"

echo "== F: publicar e salvar em paralelo: o publicado bate com algum rascunho auditado e há um evento por publicação efetiva"
before=$(sql "select count(*) from audit_records where event='published' and entity_type='profile-section'")
for n in 1 2 3 4; do run save empresa about "F$n" & run publish empresa about & done; wait
sql "select (published_value->>'title') in (select changes->'value'->'after'->>'title' from audit_records where entity_type='profile-section' and event in ('created','updated')) from profile_sections where section='about' and establishment_id is null"
after=$(sql "select count(*) from audit_records where event='published' and entity_type='profile-section'")
echo "eventos publicados na rodada: $((after - before)) (no máximo um por publicação que encontrou diferença)"
