#!/bin/bash
set -euo pipefail
# Recria o laboratório E10 do zero (só recursos com rótulo e10) e aguarda /up.
export E10_PREFIX=${E10_PREFIX:?Informe E10_PREFIX}
dir=$(dirname "$0")
bash "$dir/e10-lab.sh" down
bash "$dir/e10-lab.sh" up
until curl -sf -o /dev/null "http://127.0.0.1:${E10_PORT:-18110}/up"; do sleep 2; done
