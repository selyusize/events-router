# Быстрый старт

## Требования

- PHP 8.1 или новее
- Composer

## Установка

```bash
composer require selyusize/events-router
```

## Первое событие

Слушатель — класс со статичным методом `handle()`:

--8<-- "getting-started/quick-start.php:listener"

Маршруты — в отдельном файле, как в Slim:

--8<-- "getting-started/events.php:example"

Создаём роутер, подключаем маршруты и рассылаем событие:

--8<-- "getting-started/quick-start.php:dispatch"

```text
--8<-- "getting-started/quick-start.out"
```

## Проверка установки

--8<-- "getting-started/check-installation.php:example"

Вывод:

```text
--8<-- "getting-started/check-installation.out"
```

## Дальше

- [Основные понятия](concepts.md): событие, слушатель, middleware.
- [Топики и шаблоны](topics.md): как описать группу событий.
- [Маршруты](routes.md): `listen()`, `group()`, `add()`.
- [Рассылка](dispatching.md) и [обработка ошибок](errors.md).
- [Воркеры](workers.md): `run()` и источники событий. Каждый пример на сайте — настоящий PHP-файл, который выполняется в CI, поэтому документация не может разойтись с кодом.
