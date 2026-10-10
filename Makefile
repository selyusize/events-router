## Все команды выполняются локально. Чтобы запустить в docker-окружении,
## передай префикс: make check RUN="dl exec"
RUN ?=
COMPOSER = $(RUN) composer

.PHONY: install update lint cs cs-fix psalm test test-coverage check bench clear docs-install docs-api docs docs-serve docs-serve-en

install:
	$(COMPOSER) install

update:
	$(COMPOSER) update

lint:
	$(COMPOSER) lint

cs:
	$(COMPOSER) cs

cs-fix:
	$(COMPOSER) cs-fix

psalm:
	$(COMPOSER) psalm

test:
	$(COMPOSER) test

test-coverage:
	$(COMPOSER) test-coverage

## Всё, что проверяет CI, одной командой
check: lint cs psalm test

## Бенчмарк против symfony/event-dispatcher, с OPcache как на проде
bench:
	$(RUN) php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 benchmarks/bench.php

clear:
	rm -rf var/ site/ docs/api/

## Документация (Zensical + phpDocumentor)
ZENSICAL = var/venv/bin/zensical

docs-install:
	python3 -m venv var/venv
	var/venv/bin/pip install -r requirements-docs.txt

docs-api:
	$(RUN) tools/build-api-docs.sh

## Русская версия в site/, английская после неё — в site/en
docs: docs-api
	$(ZENSICAL) build --strict --clean
	$(ZENSICAL) build -f mkdocs.en.yml --strict --clean

docs-serve: docs-api
	$(ZENSICAL) serve

## Английская версия сайта локально
docs-serve-en:
	$(ZENSICAL) serve -f mkdocs.en.yml
