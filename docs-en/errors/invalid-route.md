# InvalidRoute

`Selyusize\EventsRouter\Exception\InvalidRoute`

A route is declared with a mistake: a listener or middleware class is not found or does not implement the required interface, or a Bitrix event route is a pattern rather than a specific event.

## Why

The router checks classes right in `listen()` and `add()`, not at dispatch. So the mistake is visible when the routes file loads, and the stack trace has the line where it was made.

| Where | What is checked |
| --- | --- |
| `listen($pattern, $listener)` | the `$listener` class exists and implements `ListenerInterface` |
| `add($middleware)` on a route, group and router | for a class name, the class exists and implements `MiddlewareInterface`; a ready object is checked by the parameter type |
| `listen()`, `group()`, `setPrefix()` with `route_cache_file` on | called inside `loadRoutes()` |
| `loadRoutes()` with `route_cache_file` on | called once; all middleware in routes is a class name, not an object |
| `BitrixEventBridge::attach()`, `attachLazy()` | a route under the `bitrix.` prefix is a specific event `bitrix.<module>.<Event>`, without `*`, `#` and parameters |

## How to fix

- Check the class name and `use` in the routes file: most often it is a typo or a forgotten import.
- Check that the class is loaded by the composer autoloader (`composer dump-autoload`).
- Add the required interface to the class.
- With the route cache, declare everything in the routes file and load it with one `loadRoutes()`; pass middleware by class name. See [Enable the route cache](../how-to/cache.md).
- For Bitrix events write each event on its own line: `main.OnAfterUserAdd`, not `main.*`. The Bitrix `EventManager` cannot subscribe to patterns. Build module ids with a dot via `BitrixEventBridge::topic()`.

```php
<?php

// Wrong: the class does not implement ListenerInterface
final class MarkOrderPaid
{
    public static function handle(EventInterface $event): void {}
}

// Right
final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void {}
}

$events->listen('order.{order_id}.paid', MarkOrderPaid::class);
```
