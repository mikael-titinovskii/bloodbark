# Everything runs through compose. `make` targets that touch the site use two stacks:
#   dev   (project `bloodbark`)      — nginx + php-fpm + postgres for browsing, https://127.0.0.1:8000
#   e2e   (project `bloodbark-e2e`)  — same files, APP_ENV=test, own network/database/cache
#
#   make up           start the dev stack, site on https://127.0.0.1:8000
#   make down         stop and remove the dev stack (its database volume survives)
#   make db-create    create the dev and test databases if they are missing
#   make migrate      run pending Doctrine migrations
#   make lint         php-cs-fixer, phpstan, eslint, container, yaml and twig linters
#   make unit         PHPUnit (phpunit.dist.xml), test database in the dev stack
#   make assets       build the JS bundle into public/assets (vite)
#   make npm CMD="install left-pad"   one npm command, in the node service
#   make ee-up        bring the e2e environment up: containers, empty migrated database, fixtures
#   make ee           `ee-up`, then Codeception Smoke + Acceptance
#   make ee-down      stop and remove the e2e stack (its database volume survives)
#   make shell        shell in the php container (Alpine image: sh, no bash)
#   make shell CMD="php bin/console debug:router"   one command in the php container
#                     (`php` is not optional: bin/ is not executable on the bind mount)

SHELL := /bin/sh

COMPOSE := docker compose
PHP := $(COMPOSE) exec -T -e XDEBUG_MODE=off php
# npm runs in the node service, not on the host: the checkout is on the WSL filesystem and Windows
# npm cannot run package install scripts across that share.
NODE := $(COMPOSE) run --rm --no-deps node

EE := $(COMPOSE) -p bloodbark-e2e -f compose.yaml -f compose.e2e.yaml
EE_PHP := $(EE) exec -T php

.PHONY: up down db-create migrate lint unit assets assets-watch npm ee ee-up ee-down shell

up:
	$(COMPOSE) up -d --wait

down:
	$(COMPOSE) down

db-create:
	$(PHP) php bin/console doctrine:database:create --if-not-exists
	$(PHP) php bin/console doctrine:database:create --if-not-exists --env=test

migrate:
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

lint:
	$(PHP) composer cs:check
	$(PHP) composer phpstan
	$(NODE) npm run lint
	$(PHP) php bin/console lint:container
	$(PHP) php bin/console lint:yaml config/ translations/
	$(PHP) php bin/console lint:twig templates/

unit:
	$(PHP) php bin/phpunit

# The bundle is a build artifact in public/assets, which nginx serves straight off the bind mount,
# so a rebuild needs no restart.
assets:
	$(NODE) npm run build

# Same build, rebuilding on every change under assets/. Leave it running in its own terminal.
assets-watch:
	$(NODE) npm run watch

# `make npm CMD="install left-pad"`. Packages land in node_modules on the bind mount (git-ignored);
# the cache in var/npm-cache survives between one-off containers.
npm:
	$(NODE) npm $(CMD)

ee-up:
	$(EE) up -d --wait
	$(EE_PHP) php bin/console doctrine:database:drop --force --if-exists
	$(EE_PHP) php bin/console doctrine:database:create
	$(EE_PHP) php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(EE_PHP) php bin/console cache:clear
	$(EE_PHP) php bin/console doctrine:fixtures:load --no-interaction --append

ee-down:
	$(EE) down

# `ee-up` gives every run the same starting point: the e2e stack's own database, rebuilt from the
# migrations and seeded from src/DataFixtures. Nothing else has touched it — it lives in a volume of
# its own — so a test may write whatever it likes. The cache is cleared because a test-env container
# compiled by `make unit` (same var/cache/test) can outlive a code change.
ee: ee-up
	$(EE_PHP) php vendor/bin/codecept run

shell:
	$(COMPOSE) exec $(if $(strip $(CMD)),-T -e XDEBUG_MODE=off) php $(or $(strip $(CMD)),sh)
