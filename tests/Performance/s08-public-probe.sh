#!/bin/bash
# S08: nenhuma mídia privada é acessível sem autorização (laboratório prospera-s08-lab, com mídia já enviada) e nenhum
# temporário sobra. Esperado: nenhum 200 com imagem, rotas de arquivo sem login redirecionam, arquivos = linhas.
docker exec prospera-s08-lab-app sh -c '
cd /app
o=$(find storage/app/private/media -type f | head -1); v=$(find storage/app/media-variants -type f | head -1)
bad=0
for u in "/storage/$o" "/storage/$v" "/$o" "/$v" "/storage/app/private/media/$(basename $o)" "/media-variants/$(basename $v)" "/storage/media/$(basename $v)"; do
  code=$(curl -s -o /tmp/b -w "%{http_code} %{content_type}" "http://127.0.0.1:8000$u"); echo "$u -> $code"
  case "$code" in 200*image*) bad=1;; esac
done
for u in /contas/grupo-gartan/empresa/gartan-roupas/midia/1/variantes/w160 /contas/grupo-gartan/empresa/gartan-roupas/midia/1/original; do
  code=$(curl -s -o /tmp/b -w "%{http_code}" "http://127.0.0.1:8000$u"); echo "sem login $u -> $code"; [ "$code" = 302 ] || bad=1
done
find public -type f \( -iname "*.jpg" -o -iname "*.png" -o -iname "*.webp" \) -not -path "public/assets/*" -not -path "public/build/*" | head -1 | grep . && bad=1
tmp=$(find storage /tmp -maxdepth 3 \( -name "*.tmp" -o -name "*.part" \) -type f | wc -l)
echo "temporários: $tmp; arquivos de mídia: $(find storage/app/private/media storage/app/media-variants -type f | wc -l)"
[ "$bad" = 0 ] && [ "$tmp" = 0 ] && echo "RESULTADO: nenhuma mídia privada exposta, nenhum temporário" || echo "FALHA"'
docker exec prospera-s08-lab-db psql -U postgres s08 -Atc "select 'linhas (assets+variantes): '||((select count(*) from media_assets)+(select count(*) from media_variants))"
