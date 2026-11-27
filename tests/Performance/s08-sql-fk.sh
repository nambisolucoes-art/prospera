q() { docker exec prospera-s08-lab-db psql -U postgres s08 -At -c "$1" 2>&1 | head -2 | cut -c1-170; }
acc=1; ex=$(docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "select id from companies where code='gartan-express'"); un=$(docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "select id from establishments limit 1")
b=$(docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "select id from companies where code='empresa-b'")
echo "-- estabelecimento de outra empresa (mesma conta)"
q "insert into media_assets (account_id,company_id,establishment_id,purpose,original_path,mime,bytes,width,height,sha256,status,uploaded_by,created_at,updated_at) values ($acc,$ex,$un,'gallery','x/7','image/png',10,1,10,repeat('a',64),'ready',1,now(),now())"
echo "-- cria mídia válida e tenta variante com empresa errada"
id=$(docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "insert into media_assets (account_id,company_id,purpose,original_path,mime,bytes,width,height,sha256,status,uploaded_by,created_at,updated_at) values ($acc,$(docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "select id from companies where code='gartan-roupas'"),'gallery','x/8','image/png',10,1,10,repeat('a',64),'ready',1,now(),now()) returning id" | head -1)
q "insert into media_variants (account_id,company_id,media_asset_id,kind,public_path,mime,bytes,width,height,created_at,updated_at) values ($acc,$ex,$id,'w320','v/z','image/webp',1,1,1,now(),now())"
echo "-- excluir mídia com variante (RESTRICT)"; q "insert into media_variants (account_id,company_id,media_asset_id,kind,public_path,mime,bytes,width,height,created_at,updated_at) values ($acc,$(docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "select company_id from media_assets where id=$id"),$id,'w320','v/ok','image/webp',1,1,1,now(),now())"; q "delete from media_assets where id=$id"
q "delete from media_variants where public_path='v/ok'"; q "delete from media_assets where original_path='x/8'"
