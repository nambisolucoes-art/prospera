#!/bin/bash
set -euo pipefail
# PHPUnit em container descartável sem rede, sobre cópia somente leitura. O bootstrap impõe SQLite em memória,
# mesmo com DB_CONNECTION=pgsql herdado. Argumentos são repassados ao PHPUnit.
source_dir=$(realpath "$(dirname "$0")/../..")
docker run --rm --network none --label prospera.lab=e10 -v "$source_dir:/source:ro" \
    -e DB_CONNECTION=pgsql -e DB_HOST=nowhere --entrypoint sh prospera-app:latest -c '
    set -eu
    for path in app bootstrap config database docker docs resources routes public tests; do
        rm -rf "/app/$path"; cp -R "/source/$path" "/app/$path"
    done
    cp /source/phpunit.xml /source/phpstan.neon /source/pint.json /source/Dockerfile /app/
    rm -f /app/.env /app/bootstrap/cache/config.php /app/bootstrap/cache/routes*.php
    cd /app
    echo APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= > .env
    php -d memory_limit=1G vendor/bin/phpunit "$@"
' sh "$@"
