## Все команды выполняются локально. Чтобы запустить в docker-окружении,
## передай префикс: make check RUN="dl exec"
RUN ?=
COMPOSER = $(RUN) composer

.PHONY: install update lint cs cs-fix psalm test test-coverage check clear

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
	rm -rf var/
