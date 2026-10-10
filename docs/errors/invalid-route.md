# InvalidRoute

`Selyusize\EventsRouter\Exception\InvalidRoute`

Маршрут объявлен с ошибкой: класс слушателя или middleware не найден или не реализует нужный интерфейс.

## Почему

Роутер проверяет классы сразу в `listen()` и `add()`, а не при рассылке. Так ошибка видна при загрузке файла маршрутов, и в стеке вызовов есть строка, где она допущена.

| Где | Что проверяется |
| --- | --- |
| `listen($pattern, $listener)` | класс `$listener` существует и реализует `ListenerInterface` |
| `add($middleware)` у маршрута, группы и роутера | если передано имя класса — класс существует и реализует `MiddlewareInterface`; готовый объект проверяет тип параметра |

## Как исправить

- Проверьте имя класса и `use` в файле маршрутов: чаще всего это опечатка или забытый импорт.
- Проверьте, что класс загружается автозагрузчиком composer (`composer dump-autoload`).
- Добавьте классу нужный интерфейс.

```php
<?php

// Неверно: класс не реализует ListenerInterface
final class MarkOrderPaid
{
    public static function handle(EventInterface $event): void {}
}

// Верно
final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void {}
}

$events->listen('order.{order_id}.paid', MarkOrderPaid::class);
```
