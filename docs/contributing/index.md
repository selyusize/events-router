# Разработка

## Окружение

```bash
git clone https://github.com/selyusize/events-router.git
cd events-router
make install
```

## Проверки

```bash
make check     # всё, что проверяет CI: phplint, php-cs-fixer, Psalm, PHPUnit
make cs-fix    # исправить стиль кода
```

В docker-окружении любую цель можно запустить с префиксом: `make check RUN="dl exec"`.

## Соглашения

Часть соглашений проверяется тестом `tests/Unit/ArchitectureTest.php`:

- конкретные классы объявлены `final`;
- исключения реализуют `ExceptionInterface`;
- у каждого исключения есть страница в `docs/errors/`;
- путь к файлу соответствует неймспейсу (PSR-4).

Стиль кода — `.php-cs-fixer.dist.php`, статический анализ — Psalm, уровень 1.
