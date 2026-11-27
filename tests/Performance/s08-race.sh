#!/bin/bash
# S08: banco e concorrência reais no laboratório descartável prospera-s08-lab (PostgreSQL 18, FrankenPHP, 2 workers).
# Cada cliente é um processo PHP próprio. Não toca em nada fora do laboratório.
cd "$(dirname "$0")/../.." || exit 1
docker cp tests/Performance/s08-race.php prospera-s08-lab-app:/tmp/s08-race.php
run() { docker exec -e DB_DATABASE=s08 prospera-s08-lab-app php /tmp/s08-race.php "$@"; }
sql() { docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "$1"; }
files() { docker exec prospera-s08-lab-app sh -c 'find /app/storage/app/private/media /app/storage/app/media-variants -type f 2>/dev/null | wc -l'; }
tmpfiles() { docker exec prospera-s08-lab-app sh -c 'find /tmp /app/storage -name "php*" -newer /tmp/s08-race.php -type f 2>/dev/null | wc -l'; }
C=/tmp/corpus

echo "== A: mesmo arquivo, 8 envios simultâneos (mesma empresa)"
for n in 1 2 3 4 5 6 7 8; do run upload admin empresa $C/jpeg-1600x1200.jpg & done | sed 's/id=[0-9]*/id=N/; s/[0-9]*ms/Xms/; s/pico=[0-9.]*/pico=X/' | sort | uniq -c; wait
sql "select count(*) as linhas, count(distinct original_path) as caminhos, count(distinct sha256) as hashes from media_assets"
sql "select count(*) as variantes_orfas from media_variants v left join media_assets a on a.id=v.media_asset_id where a.id is null"

echo "== B: arquivos diferentes, mesma empresa, 6 simultâneos"
for f in jpeg-12mp.jpg jpeg-12mp-progressivo.jpg png-grande-8mp.png webp-12mp.webp png-alpha.png jpeg-17mp.jpg; do run upload admin empresa $C/$f & done | sed 's/[0-9]*ms/Xms/' | sort | uniq -c; wait

echo "== C: tenants distintos (A e B) em paralelo"
for n in 1 2 3; do run upload admin empresa $C/jpeg-1600x1200.jpg & run upload b empresa $C/jpeg-1600x1200.jpg & done | sed 's/id=[0-9]*/id=N/; s/[0-9]*ms/Xms/; s/pico=[0-9.]*/pico=X/' | sort | uniq -c; wait
sql "select account_id, count(*) from media_assets group by 1 order by 1"

echo "== D: envio simultâneo à exclusão e edição da mesma mídia"
id=$(run upload admin guanambi $C/jpeg-1600x1200.jpg | sed -n 's/.*id=\([0-9]*\).*/\1/p')
( run delete admin guanambi $id & run update admin guanambi $id "texto novo" & run update admin guanambi $id "outro texto" & run upload admin guanambi $C/png-alpha.png & wait ) | sed 's/[0-9]*ms/Xms/; s/pico=[0-9.]*/pico=X/' | sort | uniq -c
sql "select count(*) from media_assets where id=$id"
sql "select count(*) as variantes_sem_asset from media_variants v left join media_assets a on a.id=v.media_asset_id where a.id is null"

echo "== E: falhas em paralelo (truncado, 48 MP, SVG) misturadas a válidos"
before=$(sql "select count(*) from media_assets"); fb=$(files)
for f in jpeg-truncado.jpg jpeg-48mp.jpg svg-com-script.svg jpeg-1600x1200.jpg jpeg-truncado.jpg jpeg-48mp.jpg png-alpha.png; do run upload admin empresa $C/$f & done | sed 's/id=[0-9]*/id=N/; s/[0-9]*ms/Xms/; s/pico=[0-9.]*/pico=X/' | cut -c1-90 | sort | uniq -c; wait
echo "linhas novas: $(( $(sql 'select count(*) from media_assets') - before )) (esperado 2); arquivos novos: $(( $(files) - fb ))"
sql "select count(*) from media_assets a where not exists (select 1 from media_variants v where v.media_asset_id=a.id)"

echo "== F: integridade final e resíduos"
sql "select a.id from media_assets a where a.status='ready' and not exists (select 1 from media_variants v where v.media_asset_id=a.id)"
docker exec prospera-s08-lab-app php artisan media:purge --dry-run 2>&1 | tail -6
echo "temporários php novos: $(tmpfiles)"
