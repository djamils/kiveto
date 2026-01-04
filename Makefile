##
## VARIABLES
##
HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)

DOCKER_COMPOSE = HOST_UID=$(HOST_UID) HOST_GID=$(HOST_GID) docker compose
EXEC_PHP = $(DOCKER_COMPOSE) exec -T php-fpm
COMPOSER = $(EXEC_PHP) composer
SYMFONY = $(EXEC_PHP) bin/console
SYMFONY_TEST = $(EXEC_PHP) bin/console --env=test --no-debug

APP_HOST ?= localhost
APP_PORT ?= 81
APP_URL  ?= http://$(APP_HOST):$(APP_PORT)

LOG_FILE ?= var/log/make.log

PHPSTAN_MEMORY ?= 512M

# Verbose mode: make reset V=1
V ?= 0

# Disable colors: make reset NO_COLOR=1
NO_COLOR ?= 0

# Remove "Entering/Leaving directory" noise (from recursive make)
MAKEFLAGS += --no-print-directory

# Use bash for consistent behavior
SHELL := /bin/bash

ifeq ($(NO_COLOR),1)
	C_RESET :=
	C_GREEN :=
	C_YELLOW :=
	C_BLUE :=
	C_RED :=
else
	C_RESET  := \033[0m
	C_GREEN  := \033[0;32m
	C_YELLOW := \033[0;33m
	C_BLUE   := \033[0;34m
	C_RED    := \033[0;31m
endif

# Prefix to silence command echo in normal mode
ifeq ($(V),1)
	Q :=
else
	Q := @
endif

define step
printf "\n$(C_BLUE)→ %s$(C_RESET)\n" "$(1)"
endef

define ok
printf "$(C_GREEN)✓ %s$(C_RESET)\n" "$(1)"
endef

define warn
printf "$(C_YELLOW)! %s$(C_RESET)\n" "$(1)"
endef

define fail
printf "$(C_RED)✗ %s$(C_RESET)\n" "$(1)"
endef

# Run command quietly (log to file) unless V=1
define run
if [ "$(V)" = "1" ]; then \
  $(1); \
else \
  mkdir -p "$(dir $(LOG_FILE))"; \
  $(1) >>"$(LOG_FILE)" 2>&1; \
fi
endef

define run_live
$(1)
endef

define tail_log
echo ""
echo "Last logs ($(LOG_FILE)):"
tail -n 60 "$(LOG_FILE)" 2>/dev/null || true
echo ""
endef

##
## PHONY TARGETS
##
.PHONY: help \
	build kill install reset clean start start-containers stop vendor wait-db init-db check-web ready \
	ci phpstan phpcs phpcbf php-cs-fixer php-cs-fixer.dry-run test test-coverage \
	migrations identity-access-migrations identity-access-migrations shared-migrations \
	drop-db create-db migrate-db reset-db drop-test-db create-test-db migrate-test-db reset-test-db \
	load-fixtures

##
## HELP
##
help:
	@echo "Targets:"
	@echo "  reset           clean + kill + install"
	@echo "  install         build + start + vendor + wait-db + init-db + ready"
	@echo "  start/stop      start or stop containers"
	@echo "  kill            down --volumes --remove-orphans"
	@echo "  vendor          composer install"
	@echo "  init-db         drop/create/migrate"
	@echo "  ci              QA pipeline"
	@echo ""
	@echo "Options:"
	@echo "  V=1             verbose output"
	@echo "  NO_COLOR=1      disable colors"
	@echo "  LOG_FILE=...    change log path (default: var/log/make.log)"

##
## INSTALL / RESET
##
reset:
	@rm -f "$(LOG_FILE)"
	@$(call step,Resetting environment...)
	@set -e; \
	$(MAKE) clean; \
	$(MAKE) kill; \
	$(MAKE) install
	@$(call ok,Reset complete)

install:
	@set -e; \
	$(MAKE) build; \
	$(MAKE) start-containers; \
	$(MAKE) vendor; \
	$(MAKE) wait-db; \
	$(MAKE) init-db; \
	$(MAKE) migrate-db; \
	$(MAKE) load-fixtures; \
	$(MAKE) ready

clean:
	@$(call step,Cleaning project (vendor/cache/log)...)
	@rm -rf ./vendor/ ./var/cache/* ./var/log/*
	@$(call ok,Clean complete)

build:
	@$(call step,Building Docker images...)
	$(Q)$(call run,$(DOCKER_COMPOSE) build)
	@$(call ok,Images built)

start-containers:
	@$(call step,Starting containers...)
	$(Q)$(call run,$(DOCKER_COMPOSE) up -d --remove-orphans --force-recreate)
	@$(call ok,Containers started)

start: start-containers ready

stop:
	@$(call step,Stopping containers...)
	$(Q)$(call run,$(DOCKER_COMPOSE) stop)
	@$(call ok,Containers stopped)

kill:
	@$(call step,Stopping and removing containers...)
	$(Q)$(call run,$(DOCKER_COMPOSE) down --volumes --remove-orphans)
	@$(call ok,Containers removed)

##
## UTILS
##
vendor:
	@$(call step,Installing PHP dependencies...)
	$(Q)$(call run,$(COMPOSER) install --no-interaction --prefer-dist --no-progress)
	@$(call ok,Dependencies installed)

wait-db:
	@$(call step,Waiting for MySQL to be ready...)
	@MYSQL_PWD='k!v3t0' ; \
	while ! $(DOCKER_COMPOSE) exec -T db mysqladmin ping -h 127.0.0.1 -uroot --silent >/dev/null 2>&1; do \
		printf "."; \
		sleep 1; \
	done; \
	echo ""
	@$(call ok,MySQL is ready)

drop-db:
	@$(call step,Dropping database...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:database:drop --if-exists --force)
	@$(call ok,Database dropped)

create-db:
	@$(call step,Creating database...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:database:create --if-not-exists)
	@$(call ok,Database created)

migrate-db:
	@$(call step,Running migrations...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:migrations:migrate --no-interaction --allow-no-migration)
	@$(call ok,Migrations executed)

reset-db: drop-db create-db migrate-db load-fixtures
	@$(call ok,Database reset complete)

init-db: drop-db create-db
	@$(call ok,Database initialization complete)

drop-test-db:
	@$(call step,Dropping TEST database...)
	$(Q)$(call run_live,$(SYMFONY_TEST) doctrine:database:drop --if-exists --force)
	@$(call ok,TEST database dropped)

create-test-db:
	@$(call step,Creating TEST database...)
	$(Q)$(call run_live,$(SYMFONY_TEST) doctrine:database:create --if-not-exists)
	@$(call ok,TEST database created)

migrate-test-db:
	@$(call step,Running TEST migrations...)
	$(Q)$(call run_live,$(SYMFONY_TEST) doctrine:migrations:migrate --no-interaction --allow-no-migration)
	@$(call ok,TEST migrations executed)

reset-test-db: drop-test-db create-test-db migrate-test-db
	@$(call ok,TEST database reset complete)

load-fixtures:
	@$(call step,Loading fixtures (dev) with Foundry...)
	$(Q)$(call run_live,$(SYMFONY) foundry:load-fixtures --append dev --no-interaction --quiet)
	@$(call ok,Fixtures loaded)

migrations: identity-access-migrations translations-migrations shared-migrations

identity-access-migrations:
	@$(call step,Generating migrations for IdentityAccess...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:migrations:diff --no-interaction --allow-empty-diff --formatted --namespace='DoctrineMigrations\IdentityAccess' --filter-expression='/^identity_access__/')
	@$(call ok,IdentityAccess migrations generated)

translations-migrations:
	@$(call step,Generating migrations for Translation...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:migrations:diff --no-interaction --allow-empty-diff --formatted --namespace='DoctrineMigrations\Translation' --filter-expression='/^translation__/')
	@$(call ok,Translation migrations generated)

shared-migrations:
	@$(call step,Generating migrations for Shared (technical tables)...)
	$(Q)$(call run_live,$(SYMFONY) doctrine:migrations:diff --no-interaction --allow-empty-diff --formatted --namespace='DoctrineMigrations\Shared' --filter-expression='/^shared__/')
	@$(call ok,Shared migrations generated)

check-web:
	@command -v curl >/dev/null 2>&1 || exit 0
	@curl -fsS "$(APP_URL)" >/dev/null 2>&1

ready:
	@$(call step,Finalizing...)
	@if $(MAKE) check-web; then \
		echo ""; \
		echo "=============================================="; \
		printf "$(C_GREEN)Environment ready$(C_RESET)\n"; \
		echo "----------------------------------------------"; \
		echo "Application URL: $(APP_URL)"; \
		echo "=============================================="; \
		echo ""; \
	else \
		$(call warn,Application may not be reachable yet at $(APP_URL)); \
		$(call warn,Check logs: docker-compose logs -f nginx php-fpm); \
		echo ""; \
		echo "=============================================="; \
		printf "$(C_YELLOW)Environment started$(C_RESET)\n"; \
		echo "----------------------------------------------"; \
		echo "Application URL:"; \
		echo "  $(APP_URL)"; \
		echo "=============================================="; \
		echo ""; \
	fi

##
## QUALITY ASSURANCE
##
ci: php-cs-fixer.dry-run phpcs phpstan test

phpstan:
	@$(call step,Running PHPStan...)
	$(Q)$(EXEC_PHP) vendor/bin/phpstan analyse --memory-limit=$(PHPSTAN_MEMORY)
	@$(call ok,PHPStan passed)

phpcs:
	@$(call step,Running PHPCS...)
	$(Q)$(EXEC_PHP) vendor/bin/phpcs
	@$(call ok,PHPCS passed)

phpcbf:
	@$(call step,Running PHPCBF...)
	$(Q)$(EXEC_PHP) vendor/bin/phpcbf src/ tests/ -v
	@$(call ok,PHPCBF finished)

php-cs-fixer:
	@$(call step,Running PHP-CS-Fixer...)
	$(Q)$(EXEC_PHP) vendor/bin/php-cs-fixer fix --verbose
	@$(call ok,PHP-CS-Fixer finished)

php-cs-fixer.dry-run:
	@$(call step,Running PHP-CS-Fixer (dry-run)...)
	$(Q)$(EXEC_PHP) vendor/bin/php-cs-fixer fix --verbose --diff --dry-run
	@$(call ok,PHP-CS-Fixer dry-run passed)

test: reset-test-db
	@$(call step,Running PHPUnit...)
	$(Q)$(EXEC_PHP) bin/phpunit --colors=always
	@$(call ok,Tests passed)

test-coverage: reset-test-db
	@$(call step,Running PHPUnit with coverage...)
	$(Q)$(EXEC_PHP) bin/phpunit --colors=always --coverage-html coverage --coverage-filter src/
	@$(call ok,Coverage generated (coverage/))
