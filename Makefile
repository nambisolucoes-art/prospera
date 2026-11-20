# Prospera: ambiente Docker local
#
# O código e os assets ficam dentro da imagem. Ao subir, docker/start.sh executa
# as migrations, permissions:sync e audit:check. Por isso o fluxo principal é make up.

COMPOSE := docker compose
APP     := $(COMPOSE) exec app
ARTISAN := $(APP) php artisan

.DEFAULT_GOAL := help

.PHONY: help up update down restart build rebuild ps logs logs-all shell tinker art \
        migrate migrate-status rollback fresh permissions-sync audit-check clear \
        test lint create-user prepare-sqlite

help: ## Lista os comandos disponíveis
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z_-]+:.*## / { printf "  \033[32m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# Aplicação

up: ## Sobe tudo, recompila a imagem e os assets se o código mudou e roda as migrations
	$(COMPOSE) up --build --wait
	@echo "Aplicação: http://localhost:$${APP_PORT:-8000}  Mailpit: http://localhost:$${MAILPIT_PORT:-8025}"

update: ## Atualiza o código pelo git (somente fast-forward) e executa make up
	git pull --ff-only
	@$(MAKE) --no-print-directory up

down: ## Para os containers e preserva os volumes
	$(COMPOSE) down

restart: ## Reinicia o app sem rebuild e roda as migrations da inicialização
	$(COMPOSE) restart app

build: ## Somente reconstrói a imagem, sem subir
	$(COMPOSE) build app

rebuild: ## Reconstrói a imagem sem cache e sobe
	$(COMPOSE) build --no-cache app
	@$(MAKE) --no-print-directory up

ps: ## Mostra o estado dos containers
	$(COMPOSE) ps

logs: ## Acompanha os logs do app
	$(COMPOSE) logs -f --tail=100 app

logs-all: ## Acompanha os logs de todos os serviços
	$(COMPOSE) logs -f --tail=100

shell: ## Abre um shell no container do app
	$(APP) sh

tinker: ## Abre o Tinker
	$(ARTISAN) tinker

art: ## Executa um comando Artisan. Uso: make art ARGS="route:list"
	@test -n "$(ARGS)" || { echo 'Informe ARGS, por exemplo: make art ARGS="route:list"' >&2; exit 1; }
	$(ARTISAN) $(ARGS)

# Banco de dados

migrate: ## Executa as migrations pendentes no container em execução
	$(ARTISAN) migrate --force

migrate-status: ## Mostra o estado das migrations
	$(ARTISAN) migrate:status

rollback: ## Desfaz o último lote de migrations
	$(ARTISAN) migrate:rollback

fresh: ## APAGA todas as tabelas e roda as migrations de novo
	@printf 'Isso apaga todos os dados do banco local. Digite sim para continuar: '; read answer; [ "$$answer" = sim ] || { echo Cancelado.; exit 1; }
	$(ARTISAN) migrate:fresh --force
	$(ARTISAN) permissions:sync --no-interaction

permissions-sync: ## Sincroniza o catálogo de permissões
	$(ARTISAN) permissions:sync

audit-check: ## Verifica a cobertura da auditoria
	$(ARTISAN) audit:check

clear: ## Limpa os caches do Laravel
	$(ARTISAN) optimize:clear

# Qualidade

test: ## Roda os testes, que sempre usam SQLite em memória. Filtro: make test ARGS="--filter=Etiqueta"
	$(ARTISAN) test $(ARGS)

lint: ## Verifica o estilo PHP com o Pint
	$(APP) ./vendor/bin/pint --test

# Usuários e banco em arquivo

create-user: ## Cria um usuário
	$(ARTISAN) auth:create-user

prepare-sqlite: ## Cria e migra o banco SQLite separado em storage/app
	$(ARTISAN) db:prepare-sqlite
