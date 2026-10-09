## Все команды выполняются локально. Чтобы запустить в docker-окружении,
## передай префикс: make check RUN="dl exec"
RUN ?=
COMPOSER = $(RUN) composer

.PHONY: install update lint cs cs-fix psalm test test-coverage check clear docs-install docs-api docs docs-serve

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

clear:
	rm -rf var/ site/ docs/api/

## Документация (Zensical + phpDocumentor)
ZENSICAL = var/venv/bin/zensical

docs-install:
	python3 -m venv var/venv
	var/venv/bin/pip install -r requirements-docs.txt

docs-api:
	$(RUN) tools/build-api-docs.sh

docs: docs-api
	$(ZENSICAL) build --strict --clean

docs-serve: docs-api
	$(ZENSICAL) serve
