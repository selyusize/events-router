# InvalidRoute

`Selyusize\EventsRouter\Exception\InvalidRoute`

Маршрут объявлен с ошибкой: класс слушателя или middleware не найден или не реализует нужный интерфейс, либо маршрут события Bitrix — шаблон, а не конкретное событие.

## Почему

Роутер проверяет классы сразу в `listen()` и `add()`, а не при рассылке. Так ошибка видна при загрузке файла маршрутов, и в стеке вызовов есть строка, где она допущена.

| Где | Что проверяется |
| --- | --- |
| `listen($pattern, $listener)` | класс `$listener` существует и реализует `ListenerInterface` |
| `add($middleware)` у маршрута, группы и роутера | если передано имя класса — класс существует и реализует `MiddlewareInterface`; готовый объект проверяет тип параметра |
| `listen()`, `group()`, `setPrefix()` при включённом `route_cache_file` | вызваны внутри `loadRoutes()` |
| `loadRoutes()` при включённом `route_cache_file` | вызван один раз; все middleware в маршрутах — именем класса, а не объектом |
| `BitrixEventBridge::attach()`, `attachLazy()` | маршрут под префиксом `bitrix.` — конкретное событие `bitrix.<модуль>.<Событие>`, без `*`, `#` и параметров |

## Как исправить

- Проверьте имя класса и `use` в файле маршрутов: чаще всего это опечатка или забытый импорт.
- Проверьте, что класс загружается автозагрузчиком composer (`composer dump-autoload`).
- Добавьте классу нужный интерфейс.
- С кэшем маршрутов объявляйте всё в файле маршрутов и подключайте его одним `loadRoutes()`; middleware передавайте именем класса — см. [Включить кэш маршрутов](../how-to/cache.md).
- Для событий Bitrix пишите каждое событие отдельной строкой: `main.OnAfterUserAdd`, а не `main.*`. `EventManager` Bitrix не умеет подписываться на шаблоны. Id модуля с точкой собирайте через `BitrixEventBridge::topic()`.

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
