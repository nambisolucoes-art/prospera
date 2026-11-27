#!/usr/bin/env bash
# Executa um comando dentro da imagem `dev` do Dockerfile (PHP 8.5, FrankenPHP e as extensões do projeto).
# Uso: .github/ci/php.sh composer lint:check
# Só variáveis descartáveis de CI entram no contêiner: nunca um .env, segredo ou recurso produtivo.
set -euo pipefail

args=(
  --rm
  --user "$(id -u):$(id -g)"
  -e HOME=/tmp
  -e COMPOSER_CACHE_DIR=/composer-cache
  -e APP_ENV=testing
  -e APP_KEY
  -e MAIL_MAILER=array
  -e CACHE_STORE=array
  -e SESSION_DRIVER=array
  -e QUEUE_CONNECTION=sync
  -v "$PWD:/app"
  -v "${COMPOSER_CACHE_HOST:-$RUNNER_TEMP/composer-cache}:/composer-cache"
  -w /app
)

# Banco PostgreSQL descartável do job (serviço do próprio runner), quando o job o declara.
if [ -n "${CI_DB_HOST:-}" ]; then
  args+=(--network host -e DB_CONNECTION=pgsql -e "DB_HOST=$CI_DB_HOST" -e DB_PORT=5432
    -e DB_DATABASE=prospera_ci -e DB_USERNAME=postgres -e DB_PASSWORD=ci)
fi

exec docker run "${args[@]}" "${CI_IMAGE:-prospera-ci:dev}" "$@"
