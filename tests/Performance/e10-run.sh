#!/bin/bash
set -euo pipefail

# Executa um cliente E10 dentro do laboratório descartável, com a versão atual dos scripts.
prefix=${E10_PREFIX:?Informe E10_PREFIX}
[[ "$prefix" == prospera-e10-lab-* ]] || { echo 'Prefixo de laboratório inválido'; exit 1; }
source_dir=$(realpath "$(dirname "$0")/../..")
docker cp "$source_dir/tests/Performance/e10" "$prefix-app:/app/tests/Performance/" >/dev/null
docker exec -e HARNESS_URL="${HARNESS_URL:-http://127.0.0.1:8000}" -e E10_REQUESTS="${E10_REQUESTS:-}" -e E10_LABEL="${E10_LABEL:-}" "$prefix-app" php "tests/Performance/e10/$1.php"
