# events-router

Every system starts not with code, but with what happens in it. An order is placed, paid, cancelled. A user signs up. A payment fails. Someone has to react to each of these events: take goods off the stock, send an email, accrue bonuses, notify the CRM.

While there are five events, all of this lives in one method. When there are fifty, the reactions spread across the code, and nobody can answer "what actually happens when an order is paid?" without searching the project.

events-router solves exactly this. All reactions to events are described in one file, the same way as HTTP routes in Slim:

```php
<?php

return static function (EventRouter $events): void {

    $events->setPrefix('shop');

    $events->group('order', static function (RouteGroup $group): void {
        $group->listen('created', Listener\Order\ReserveStock::class);
        $group->listen('created', Listener\Order\SendConfirmationEmail::class);

        $group->group('{order_id}', static function (RouteGroup $order): void {
            $order->listen('paid', Listener\Order\MarkOrderPaid::class);
            $order->listen('paid', Listener\Bonuses\AccrueBonuses::class);
        })
            ->add(Middleware\Idempotency\IdempotencyGuard::class);
    })
        ->add(Middleware\EventLog\EventLogger::class);
};
```

Open the file and everything is visible: which events exist, who listens to them, in what order and through which middleware. No searching for attributes, no subscribers scattered across folders, no priorities to keep in your head.

!!! note "Before 1.0"
    The API may still change in minor versions. What changed and how to upgrade is in the [changelog](https://github.com/selyusize/events-router/blob/main/CHANGELOG.md) (in Russian).

## Who it is for

For PHP developers who write routes in Slim and want to write reactions to events the same way. For projects with or without Bitrix, for synchronous dispatch from code and for queue workers. If you know how `$app->group(...)->add(...)` works, you will hardly need this documentation. Hardly.

## How it differs from the usual dispatchers

| | `symfony/event-dispatcher` | `symfony/messenger` | **events-router** |
| --- | --- | --- | --- |
| How a listener is chosen | exact event name, order by priority | message class → bus/transport | **topic pattern** (`order.{id}.paid`, `order.*`) |
| Middleware | none | shared by the whole bus | **per route and per group, inherited** |
| Groups, prefixes | none | none | **yes, as in Slim** |
| Where routes are described | attributes and subscribers all over the code | routing config | **one declarative file** |

## Language

The documentation is available in Russian and English, and the library speaks both too. Exceptions and log messages are in Russian by default. To get them in English, set the `locale` config key:

```php
$events = EventRouterFactory::create($container, ['locale' => 'en']);
```

Details are in [Configuration](reference/config.md#locale).

## How the documentation is organised

The documentation is split by why you came here.

| Section | When to open it |
| --- | --- |
| [Tutorial](tutorial/index.md) | you are here for the first time and want a working router in ten minutes |
| [How-to guides](how-to/index.md) | you have a specific task: plug in a container, set up the log, run a worker |
| [Reference](reference/index.md) | you need precision: pattern syntax, route methods, config keys, report fields |
| [Explanation](explanation/index.md) | you want to understand why the library is built this way |
| [Errors](errors/index.md) | the library threw an exception, and its message links here |

Every example on this site is a real PHP file. CI runs it and compares its output with what is printed on the page. The documentation cannot silently drift away from the code: the build simply fails.
