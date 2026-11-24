#!/bin/bash
set -uo pipefail

# Recria o laboratório E10 e executa as jornadas integradas em ordem. Resultados JSON vão para $E10_OUT.
export E10_PREFIX=${E10_PREFIX:?Informe E10_PREFIX}
out=${E10_OUT:-/tmp/prospera-e10/evidence/jornadas}
dir=$(dirname "$0")
mkdir -p "$out"
E10_METRICS=${E10_METRICS:-1} bash "$dir/e10-reset.sh" >/dev/null
status=0
for journey in 01-provisionamento 02-organizacao 03-acessos 04-convites 05-multicontas 06-area-trabalho \
    07-comercial 08-auditoria 09-inspecao 10-seguranca; do
    bash "$dir/e10-run.sh" "$journey" 2>>"$out/stderr.log" | tee -a "$out/resumo.log" || status=1
done
# Concorrência contra uma segunda instância com dois workers, no mesmo container descartável.
docker exec -d -e OCTANE_STATE_FILE=/tmp/e10-two.json "$E10_PREFIX-app" sh -c \
    'php artisan octane:frankenphp --host=127.0.0.1 --port=8001 --admin-port=2020 --workers=2 --max-requests=20000 > /tmp/e10-two.log 2>&1'
until docker exec "$E10_PREFIX-app" curl -sf -o /dev/null http://127.0.0.1:8001/up; do sleep 1; done
HARNESS_URL=http://127.0.0.1:8001 bash "$dir/e10-run.sh" 11-concorrencia 2>>"$out/stderr.log" | tee -a "$out/resumo.log" || status=1
# Chave de várias contas desligada: terceira instância sobre o mesmo banco e as mesmas sessões.
docker exec -d -e OCTANE_STATE_FILE=/tmp/e10-off.json -e PROSPERA_MULTIPLE_ACCOUNTS=false "$E10_PREFIX-app" sh -c \
    'php artisan octane:frankenphp --host=127.0.0.1 --port=8002 --admin-port=2021 --workers=1 --max-requests=20000 > /tmp/e10-off.log 2>&1'
until docker exec "$E10_PREFIX-app" curl -sf -o /dev/null http://127.0.0.1:8002/up; do sleep 1; done
bash "$dir/e10-run.sh" 12-chave-desligada 2>>"$out/stderr.log" | tee -a "$out/resumo.log" || status=1
# Workers persistentes: fluxos integrados alternados com um e dois workers.
requests=${E10_REQUESTS:-3000}
E10_REQUESTS=$requests E10_LABEL=um-worker bash "$dir/e10-run.sh" 13-octane 2>>"$out/stderr.log" | tee -a "$out/resumo.log" || status=1
E10_REQUESTS=$requests E10_LABEL=dois-workers HARNESS_URL=http://127.0.0.1:8001 bash "$dir/e10-run.sh" 13-octane \
    2>>"$out/stderr.log" | tee -a "$out/resumo.log" || status=1
E10_REQUESTS=${E10_SCREEN_SAMPLES:-100} E10_LABEL=base bash "$dir/e10-run.sh" 14-desempenho \
    2>>"$out/stderr.log" | tee -a "$out/resumo.log" || status=1
docker cp "$E10_PREFIX-app:/tmp/e10-results/." "$out/" >/dev/null
docker cp "$E10_PREFIX-app:/tmp/e10-state.json" "$out/../state.json" >/dev/null
docker exec "$E10_PREFIX-app" sh -c 'grep -c "local.ERROR" /tmp/e10-two.log || true' > "$out/erros-dois-workers.txt"
exit $status
