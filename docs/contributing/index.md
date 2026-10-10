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
- исключения реализуют `Contract\Exception\ExceptionInterface`;
- интерфейсы лежат только в `src/Contract/`;
- enum называются `...Enum`;
- у каждого исключения есть страница в `docs/errors/`;
- путь к файлу соответствует неймспейсу (PSR-4).

## Компоненты и их границы

Папка в `src/` — это компонент. Зависимости идут в одну сторону:

```text
Contract ← Topic ← Routing ← Dispatch ← Service ← EventRouter + EventRouterFactory
```

| Компонент | Что внутри |
| --- | --- |
| `Contract/` | **все** интерфейсы библиотеки: событие, слушатель, middleware, обработчик ошибок, источник событий, объявление маршрутов, общий интерфейс исключений |
| `Topic/` | шаблоны топиков |
| `Routing/` | данные маршрутов: объявления (`RouteGroup`, `Route`) и собранная таблица (`RouteTable` с поиском `match()`, `CompiledRoute`, `RouteMatch`) |
| `Dispatch/` | данные рассылки: отчёты, статусы, стратегия ошибок |
| `Service/` | работа: `RouteTableBuilder` строит таблицу, `Dispatcher` рассылает, обработчики ошибок пишут ошибки |
| `Source/` | реализации источников событий для воркеров (`InMemoryEventSource`); зависят только от `Contract/` |
| `EventRouter`, `EventRouterFactory` | фасад и единственное место, где части роутера создаются и связываются |
| `Psr14/` | адаптер PSR-14 поверх `EventRouter` — внешний слой, как и сам роутер |
| `Exception/` | исключения; зависят только от `Contract/` (`ExceptionInterface`) и `Documentation` |
| `Documentation` | лист: ни от кого не зависит |

Границы проверяет `tests/Unit/BoundariesTest.php`: импорт «против течения» валит тесты.

Стиль кода — `.php-cs-fixer.dist.php`, статический анализ — Psalm, уровень 1.

## Зависимости

Dependabot (`.github/dependabot.yml`) раз в неделю, по понедельникам, открывает pull request'ы с обновлениями:

| Что | Где | Как |
| --- | --- | --- |
| PHP-пакеты | `composer.json` | ограничения версий расширяются (к `^1.0` добавляется `^2.0`), а не сужаются: библиотека остаётся совместимой со старыми версиями; инструменты разработки — одним pull request'ом |
| действия CI | `.github/workflows/` | все — одним pull request'ом |
| генератор сайта | `requirements-docs.txt` | отдельным pull request'ом: версия Zensical зафиксирована, обновляем осознанно |

Каждый такой pull request проходит CI на PHP 8.1–8.4 и сборку документации. Зелёный — можно сливать.
