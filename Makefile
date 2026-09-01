# Developer entry point for local_corolair.
#
# Two tiers:
#   Tier 1 (native)  lint, cs, fix     -- no database, no container, sub-second
#   Tier 2 (docker)  setup, ci, phpunit -- full parity with GitHub Actions
#
# Quick start:
#   brew install php@8.2 composer && composer install   # once
#   make lint cs                                        # fast inner loop
#   make up setup                                       # once, a few minutes
#   make ci                                             # everything CI runs

SHELL := /bin/bash

# Homebrew keg-only PHP. Prepended here so no shell profile edit is needed.
PHP_BIN_DIR := /opt/homebrew/opt/php@8.2/bin
export PATH := $(PHP_BIN_DIR):$(PATH)

# The CI matrix. PHP version and Moodle branch are paired deliberately: Moodle 5.1
# requires PHP >= 8.2, so an 8.1 image with a 5.1 checkout fails deep inside
# `composer install` with a wall of version conflicts. Setting PHP_VERSION alone
# selects the matching branch, so the invalid pairing cannot be requested by accident.
PHP_VERSION ?= 8.2
ifeq ($(PHP_VERSION),8.1)
MOODLE_BRANCH ?= MOODLE_405_STABLE
else
MOODLE_BRANCH ?= MOODLE_501_STABLE
endif
export PHP_VERSION
export MOODLE_BRANCH

# Paths that are never ours: composer deps and the gitignored packaging copy.
PHP_FILES = $(shell find . -name '*.php' \
	-not -path './vendor/*' \
	-not -path './local_corolair/*' \
	-not -path './.git/*')

# Same flags moodle-plugin-ci passes to phpcs (see CodeCheckerCommand); the
# default standard is `moodle`, not `moodle-extra`.
# Two traps in the --ignore value, both of which make phpcs silently scan nothing
# and report success:
#   * unquoted, the shell expands vendor/* into real paths and mangles the flag;
#   * patterns are matched unanchored against the full path, so `local_corolair/*`
#     also matches this repo's own directory (moodle-local_corolair) and excludes
#     every file. The leading */ anchors it to a real path segment.
# If you change this, check the progress output shows the expected file count.
PHPCS_FLAGS = --standard=moodle --extensions=php -p -s --no-cache \
	--report-full --report-width=132 --encoding=utf-8 \
	--ignore='*/vendor/*,*/local_corolair/*'

COMPOSE = docker compose
EXEC = $(COMPOSE) exec -T ci
PLUGIN_IN_CONTAINER = /plugin
# moodle-plugin-ci copies the plugin into the Moodle tree rather than symlinking
# it, so host edits do not reach the code under test until `sync` runs.
PLUGIN_IN_MOODLE = /workspace/moodle/public/local/corolair

.DEFAULT_GOAL := help

# ---------------------------------------------------------------- tier 1 ----

.PHONY: help
help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

.PHONY: lint
lint: ## PHP syntax check (native, fast)
	@echo "==> php -l"
	@fail=0; for f in $(PHP_FILES); do php -l "$$f" > /dev/null || fail=1; done; \
		if [ $$fail -ne 0 ]; then echo "LINT FAILED"; exit 1; fi; \
		echo "OK ($(words $(PHP_FILES)) files)"

.PHONY: cs
cs: ## Moodle Code Checker (native, fast). Warnings fail, as in CI.
	@echo "==> phpcs"
	@vendor/bin/phpcs $(PHPCS_FLAGS) .

.PHONY: fix
fix: ## Auto-fix what the code checker can (native)
	@vendor/bin/phpcbf $(PHPCS_FLAGS) . || true

.PHONY: check
check: lint cs ## Tier 1: everything that needs no database

# ---------------------------------------------------------------- tier 2 ----

.PHONY: up
up: ## Start the database and CI containers
	@# --build so the running image always matches PHP_VERSION. Without it, changing
	@# PHP_VERSION silently reuses the previously built image and the PHP version
	@# drifts out of step with the Moodle branch. Cached layers make this ~1s.
	$(COMPOSE) up -d --wait --build

.PHONY: down
down: ## Stop containers (keeps the Moodle checkout)
	$(COMPOSE) down

.PHONY: clean
clean: ## Stop containers and delete the Moodle checkout volume
	$(COMPOSE) down -v

.PHONY: rebuild
rebuild: ## Rebuild the CI image (after changing PHP_VERSION)
	$(COMPOSE) build --no-cache ci

.PHONY: setup
setup: up ## Install Moodle and this plugin in the container (slow, once)
	@# Both the checkout and the database are cleared first, so switching
	@# PHP_VERSION/MOODLE_BRANCH reinstalls cleanly. Without the DROP, install
	@# aborts with "Can't create database 'moodle'; database exists".
	$(EXEC) rm -rf /workspace/moodle
	$(EXEC) mysql -h db -u root -e 'DROP DATABASE IF EXISTS moodle'
	$(EXEC) moodle-plugin-ci install --plugin $(PLUGIN_IN_CONTAINER) --db-host=db

.PHONY: shell
shell: ## Interactive shell in the CI container
	$(COMPOSE) exec ci bash

.PHONY: require-moodle
require-moodle: up
	@$(EXEC) test -x /workspace/moodle/vendor/bin/phpunit 2>/dev/null || { \
		echo ""; \
		echo "  Moodle is not fully installed in the container."; \
		echo "  An interrupted or failed 'make setup' leaves a partial tree, and the"; \
		echo "  checks below would report misleading passes against it."; \
		echo ""; \
		echo "  Run:  make setup"; \
		echo ""; \
		exit 1; \
	}

.PHONY: sync
sync: require-moodle ## Copy host edits into the Moodle tree (implied by every check below)
	@$(EXEC) sh -c 'rm -rf $(PLUGIN_IN_MOODLE) && mkdir -p $(PLUGIN_IN_MOODLE) && \
		tar -C $(PLUGIN_IN_CONTAINER) \
			--exclude=./.git --exclude=./vendor --exclude=./local_corolair \
			--exclude=./local_corolair.zip --exclude=./plans \
			-cf - . | tar -C $(PLUGIN_IN_MOODLE) -xf -'

# Individual CI steps, each matching a step in .github/workflows/moodle-plugin-ci.yml.
# The plugin path is passed explicitly on every call. On GitHub, `install` exports it
# via $GITHUB_ENV; here there is no such mechanism, and relying on state inside the
# container breaks as soon as the container is recreated.
.PHONY: phplint phpcs phpdoc validate savepoints mustache phpunit
phplint: sync    ## CI step: PHP Lint
	$(EXEC) moodle-plugin-ci phplint $(PLUGIN_IN_MOODLE)
phpcs: sync      ## CI step: Moodle Code Checker
	$(EXEC) moodle-plugin-ci phpcs --max-warnings 0 $(PLUGIN_IN_MOODLE)
phpdoc: sync     ## CI step: Moodle PHPDoc Checker
	$(EXEC) moodle-plugin-ci phpdoc --max-warnings 0 $(PLUGIN_IN_MOODLE)
validate: sync   ## CI step: Validate plugin
	$(EXEC) moodle-plugin-ci validate $(PLUGIN_IN_MOODLE)
savepoints: sync ## CI step: Check upgrade savepoints
	$(EXEC) moodle-plugin-ci savepoints $(PLUGIN_IN_MOODLE)
mustache: sync   ## CI step: Validate Mustache templates
	$(EXEC) moodle-plugin-ci mustache $(PLUGIN_IN_MOODLE)
phpunit: sync    ## Run the PHPUnit suite
	$(EXEC) moodle-plugin-ci phpunit --fail-on-warning $(PLUGIN_IN_MOODLE)

.PHONY: ci
ci: phplint phpcs phpdoc validate savepoints mustache phpunit ## Everything CI runs, plus phpunit

# --------------------------------------------------------------- package ----

.PHONY: package
package: ## Build local_corolair.zip for the Moodle plugin directory
	@# git archive packages HEAD, not the working tree, and it reads the
	@# export-ignore rules from the committed .gitattributes. Releasing with
	@# uncommitted changes would silently ship something other than what is on
	@# disk, so refuse rather than surprise.
	@if ! git diff-index --quiet HEAD -- || [ -n "$$(git ls-files --others --exclude-standard)" ]; then \
		echo "Working tree is not clean. 'git archive' packages HEAD, so the zip"; \
		echo "would not match your files. Commit (or stash) first."; \
		git status --short; \
		exit 1; \
	fi
	@rm -f local_corolair.zip
	@git archive --format=zip --prefix=local_corolair/ -o local_corolair.zip HEAD
	@echo "==> local_corolair.zip ($$(unzip -l local_corolair.zip | tail -1 | awk '{print $$2}') files)"
	@if unzip -l local_corolair.zip | grep -qE "local_corolair/(Makefile|docker-compose.yml|composer.json|.dev/|.github/)"; then \
		echo "WARNING: development files leaked into the zip; check .gitattributes"; \
		exit 1; \
	fi
	@echo "    dev files excluded, tests included:"
	@unzip -l local_corolair.zip | grep -cE "local_corolair/tests/.*\.php" | sed 's/^/      test files: /'

.PHONY: package-prod
package-prod: ## Build local_corolair-prod.zip: the production tree, from HEAD, without releasing
	@# The same transform the release workflow applies, done locally against HEAD, so a
	@# production build can be installed and tried before it exists on main.
	@#
	@# It releases nothing. Nothing is pushed, no branch moves, and main is still only ever
	@# updated by merging the generated pull request -- this only writes a zip. Use it to test an
	@# install, not to ship: a zip built here has not been through CI or review.
	@if ! git diff-index --quiet HEAD -- || [ -n "$$(git ls-files --others --exclude-standard)" ]; then \
		echo "Working tree is not clean. 'git archive' packages HEAD, so the zip"; \
		echo "would not match your files. Commit (or stash) first."; \
		git status --short; \
		exit 1; \
	fi
	@rm -rf .package-prod local_corolair-prod.zip
	@mkdir -p .package-prod
	@# export-ignore is honoured here exactly as in `package`, so development files stay out.
	@git archive --format=tar --prefix=local_corolair/ HEAD | tar -C .package-prod -xf -
	@# -i.bak rather than -i: BSD sed on macOS requires the suffix, GNU sed accepts it.
	@sed -i.bak "s/public const ENV = 'develop';/public const ENV = 'production';/" \
		.package-prod/local_corolair/classes/local/environment.php
	@rm -f .package-prod/local_corolair/classes/local/environment.php.bak
	@rm -f .package-prod/local_corolair/classes/local/hosts_dev.php
	@# Both halves of the transform are verified, for the same reason the workflow verifies them:
	@# a silent no-op here ships development endpoints to a customer.
	@if ! grep -q "public const ENV = 'production';" \
		.package-prod/local_corolair/classes/local/environment.php; then \
		echo "The environment was not switched to production. Not packaging."; \
		rm -rf .package-prod; \
		exit 1; \
	fi
	@if grep -rIn -e 'corolair\.dev' -e 'corolair\.workers' .package-prod; then \
		echo "A development host survived the transform. Not packaging."; \
		rm -rf .package-prod; \
		exit 1; \
	fi
	@cd .package-prod && zip -qr ../local_corolair-prod.zip local_corolair
	@rm -rf .package-prod
	@echo "==> local_corolair-prod.zip ($$(unzip -l local_corolair-prod.zip | tail -1 | awk '{print $$2}') files, production endpoints)"
