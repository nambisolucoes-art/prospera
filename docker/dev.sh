#!/bin/sh
# Desenvolvimento: instala as dependências nos volumes quando o lock muda e inicia o serviço pedido.
set -eu

case "${1:-}" in
    app)
        if ! cmp -s composer.lock vendor/.composer.lock; then
            composer install --no-interaction --prefer-dist
            cp composer.lock vendor/.composer.lock
        fi

        # O FrankenPHP reinicia os workers quando os arquivos de config('octane.watch') mudam.
        exec /bin/sh docker/start.sh php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=2 --caddyfile=docker/Caddyfile --watch
        ;;
    vite)
        if ! cmp -s package-lock.json node_modules/.package-lock.copy.json; then
            npm ci --no-audit --no-fund
            cp package-lock.json node_modules/.package-lock.copy.json
        fi

        # O laravel-vite-plugin grava public/hot e o @vite passa a servir os módulos com HMR.
        exec npm run dev -- --host 0.0.0.0
        ;;
    *)
        echo 'Uso: docker/dev.sh app|vite' >&2
        exit 1
        ;;
esac
