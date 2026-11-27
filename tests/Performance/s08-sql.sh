#!/bin/bash
# S08: restrições do PostgreSQL 18 por SQL direto, planos de consulta, rollback e reaplicação (laboratório prospera-s08-lab).
cd "$(dirname "$0")/../.." || exit 1
q() { docker exec prospera-s08-lab-db psql -U postgres s08 -At -c "$1" 2>&1 | head -2 | cut -c1-150; }
a=$(docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "select id, account_id from companies where code='gartan-roupas'")
c=${a%|*}; acc=${a#*|}
other=$(docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "select id from companies where code='empresa-b'")
ins="insert into media_assets (account_id,company_id,establishment_id,purpose,original_path,mime,bytes,width,height,sha256,status,uploaded_by,created_at,updated_at)"
ok="$acc,$c,null,'gallery','x/'||gen_random_uuid(),'image/jpeg',10,10,10,repeat('a',64),'ready',1,now(),now()"
echo "-- esperado: válido"; q "$ins select $ok returning id"
echo "-- propósito inválido"; q "$ins select $acc,$c,null,'banner2','x/1','image/jpeg',10,10,10,repeat('a',64),'ready',1,now(),now()"
echo "-- MIME inválido (svg)"; q "$ins select $acc,$c,null,'gallery','x/2','image/svg+xml',10,10,10,repeat('a',64),'ready',1,now(),now()"
echo "-- status inválido"; q "$ins select $acc,$c,null,'gallery','x/3','image/png',10,10,10,repeat('a',64),'deleted',1,now(),now()"
echo "-- dimensão zero"; q "$ins select $acc,$c,null,'gallery','x/4','image/png',10,0,10,repeat('a',64),'ready',1,now(),now()"
echo "-- sha256 curto"; q "$ins select $acc,$c,null,'gallery','x/5','image/png',10,1,10,'abc','ready',1,now(),now()"
echo "-- empresa de outra conta (FK composta company+account)"; q "$ins select $acc,$other,null,'gallery','x/6','image/png',10,1,10,repeat('a',64),'ready',1,now(),now()"
echo "-- estabelecimento de outra empresa (FK composta)"; q "$ins select $acc,$other,(select id from establishments where company_id=$c limit 1),'gallery','x/7','image/png',10,1,10,repeat('a',64),'ready',1,now(),now()"
echo "-- caminho original duplicado"; q "$ins select $acc,$c,null,'gallery',(select original_path from media_assets limit 1),'image/png',10,1,10,repeat('a',64),'ready',1,now(),now()"
echo "-- variante com empresa diferente do asset (FK composta)"
q "insert into media_variants (account_id,company_id,media_asset_id,kind,public_path,bytes,width,height,created_at,updated_at) select $acc,$other,(select id from media_assets limit 1),'w320','v/z',1,1,1,now(),now()"
echo "-- excluir empresa com mídia (RESTRICT)"; q "delete from companies where id=$c"
echo "-- limpa a linha válida"; q "delete from media_assets where original_path like 'x/%'"
echo "== planos"
q "explain (costs off) select * from media_assets where company_id=$c and establishment_id is null order by created_at desc limit 25" | head -1
docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "set enable_seqscan=off; explain (costs off) select * from media_assets where company_id=$c and establishment_id=1 order by created_at desc limit 25" | head -3
docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "\d media_variants" | grep -i "index\|unique\|btree" | cut -c1-140
echo "== rollback e reaplicação (apaga só a tabela do laboratório)"
docker exec prospera-s08-lab-app sh -c 'cd /app && php artisan migrate:rollback --step=1 --force 2>&1 | tail -2; php artisan migrate --force 2>&1 | tail -2'
q "select count(*) from information_schema.tables where table_name in ('media_assets','media_variants')"
