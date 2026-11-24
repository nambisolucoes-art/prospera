#!/bin/bash
set -uo pipefail

# Prova da redação dos logs de acesso (E10, segurança dos logs). Laboratório descartável, tokens sintéticos.
# Uso: E10_PREFIX=prospera-e10-lab-x E10_PORT=18111 E10_MODE=baseline|oficial|oficial-bruto bash e10-logs.sh
#   baseline       comando sem --caddyfile (configuração anterior à correção)
#   oficial        argumento --caddyfile lido do CMD do Dockerfile, saída padrão do Octane
#   oficial-bruto  igual ao oficial, com --log-level=INFO (o Octane repassa o JSON bruto do Caddy)
export E10_PREFIX=${E10_PREFIX:?Informe E10_PREFIX}
export E10_PORT=${E10_PORT:-18111}
mode=${E10_MODE:?Informe E10_MODE}
out=${E10_OUT:-/tmp/prospera-e10/logs-$mode}
dir=$(dirname "$0")
root=$(realpath "$dir/../..")
mkdir -p "$out"

args=''
if [ "$mode" != baseline ]; then
    args=$(grep -o '"--caddyfile=[^"]*"' "$root/Dockerfile" | tr -d '"')
    [ -n "$args" ] || { echo 'Dockerfile sem --caddyfile no CMD'; exit 1; }
    grep -q -- "--caddyfile=" "$root/docker/dev.sh" || { echo 'docker/dev.sh sem --caddyfile'; exit 1; }
fi
[ "$mode" = oficial-bruto ] && args="$args --log-level=INFO"
export E10_OCTANE_ARGS="$args"
echo "modo=$mode argumentos extras do Octane: ${args:-nenhum}"

bash "$dir/e10-reset.sh" >/dev/null
for journey in 01-provisionamento 02-organizacao 03-acessos 04-convites 05-multicontas 06-area-trabalho \
    07-comercial 08-auditoria 09-inspecao 10-seguranca; do
    bash "$dir/e10-run.sh" "$journey" 2>>"$out/stderr.log" | tee -a "$out/resumo.log"
done

# Requisições extras: Referer com o caminho sensível, cabeçalho CSRF sintético e links assinados.
cat > /tmp/e10-extra-$$.sh <<'INNER'
set -u
b=http://127.0.0.1:8000
tok=$(curl -s "http://mail:8025/api/v1/search?query=convite" | grep -o 'convites/[A-Za-z0-9]\{64\}' | head -1 | cut -d/ -f2)
[ -n "$tok" ] || tok=$(printf 'Q%.0s' $(seq 64))
curl -s -o /dev/null -H "Referer: $b/convites/$tok" -H 'X-XSRF-TOKEN: SINTETICOXSRFvalor1234567890' "$b/convites/$tok"
curl -s -o /dev/null -H 'Accept: application/json' -H "Referer: http://externo.test/convites/$tok?x=1" "$b/login"
curl -s -o /dev/null "$b/reset-password/SINTETICORESETtoken0987654321?email=sintetico%40e10.test"
curl -s -o /dev/null "$b/email/verify/7/SINTETICOHASHverificacao123456?expires=1999999999&signature=SINTETICOASSINATURAabcdef123456"
curl -s -o /dev/null "$b/settings/profile/email/confirm?id=9&signature=SINTETICOASSINATURA2ghijkl7890"
curl -s -o /dev/null "$b/%63onvites/SINTETICOCODIFICADOtoken1122334455"
INNER
docker cp /tmp/e10-extra-$$.sh "$E10_PREFIX-app:/tmp/extra.sh" >/dev/null
docker exec "$E10_PREFIX-app" bash /tmp/extra.sh
bash "$dir/e10-run.sh" 16-logs-limite 2>>"$out/stderr.log" | tee -a "$out/resumo.log"
rm -f /tmp/e10-extra-$$.sh
sleep 2

docker logs "$E10_PREFIX-app" > "$out/app.log" 2>&1
docker exec "$E10_PREFIX-app" sh -c 'cat storage/logs/*.log 2>/dev/null' > "$out/storage.log"
docker exec "$E10_PREFIX-app" php -r '
$tokens = [];
$list = json_decode(file_get_contents("http://mail:8025/api/v1/messages?limit=500"), true)["messages"] ?? [];
foreach ($list as $m) {
    $msg = json_decode(file_get_contents("http://mail:8025/api/v1/message/".$m["ID"]), true);
    $text = html_entity_decode(($msg["Text"] ?? "").($msg["HTML"] ?? ""));
    preg_match_all("#/(?:convites|reset-password)/([A-Za-z0-9]+)#", $text, $f);
    array_push($tokens, ...$f[1]);
}
echo implode("\n", array_unique($tokens)), "\n";' > "$out/.segredos"
printf '%s\n' ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ QQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQQ \
    SINTETICOXSRFvalor1234567890 SINTETICORESETtoken0987654321 SINTETICOHASHverificacao123456 \
    SINTETICOASSINATURAabcdef123456 SINTETICOASSINATURA2ghijkl7890 SINTETICOCODIFICADOtoken1122334455 \
    SINTETICOLIMITExxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx >> "$out/.segredos"
python3 -I "$dir/e10-logs-check.py" "$out/.segredos" "$out/app.log" "$out/storage.log" | tee "$out/resultado.txt"
rm -f "$out/.segredos"
