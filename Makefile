# One command per task; everything PHP runs through the compose `php` service.
#
#   make up           start nginx + php-fpm + postgres, site on https://127.0.0.1:8000
#   make down         stop and remove the containers (the database volume survives)
#   make db-create    create the dev and test databases if they are missing
#   make migrate      run pending Doctrine migrations
#   make lint         php-cs-fixer, phpstan, container and yaml linters
#   make unit         PHPUnit (phpunit.dist.xml)
#   make ee           Codeception suites against the running site (`ee` starts it)
#   make shell        shell in the php container (Alpine image: sh, no bash)
#   make shell CMD="php bin/console debug:router"   one command in the php container
#                     (`php` is not optional: bin/ is not executable on the bind mount)

SHELL := /bin/sh

COMPOSE := docker compose
PHP := $(COMPOSE) exec -T -e XDEBUG_MODE=off php

.PHONY: up down db-create migrate lint unit ee shell

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
	$(PHP) php bin/console lint:container
	$(PHP) php bin/console lint:yaml config/
	$(PHP) php bin/console lint:twig templates/

unit:
	$(PHP) php bin/phpunit

# The site is served from the dev container cache, which can lag behind an edit by a request;
# clearing it first keeps a run from testing yesterday's routes.
ee: up
	$(PHP) php bin/console cache:clear
	$(PHP) php vendor/bin/codecept run

shell:
	$(COMPOSE) exec $(if $(strip $(CMD)),-T -e XDEBUG_MODE=off) php $(or $(strip $(CMD)),sh)
