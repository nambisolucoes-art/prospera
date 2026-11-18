.PHONY: create-user prepare-sqlite

create-user:
	docker compose exec app php artisan auth:create-user

prepare-sqlite:
	docker compose exec app php artisan db:prepare-sqlite
