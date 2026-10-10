# Connect Bitrix events

In a Bitrix project, event handlers usually live in `init.php` or `events.php`: one `AddEventHandler(...)` line after another, classes and methods as strings, `&$arFields` by reference, cancellation via `return false` and `$APPLICATION->ThrowException()`. With ten handlers this is bearable. With a hundred, nobody can say what exactly happens when a product is saved.

The `BitrixEventBridge` moves Bitrix events into the same routes file where your own events live. Listeners, middleware, call order, log and error handling are all the same.

## 1. Describe the routes

Bitrix events go into the `bitrix` group, the topic is `<module>.<Event>`, as in Bitrix:

```php
--8<-- "bitrix/bridge.php:routes"
```

Each line is one specific event. Patterns like `sale.*` do not work here: the Bitrix `EventManager` subscribes only to exact "module + event" pairs. Such a route gives [`InvalidRoute`](../errors/invalid-route.md) when the bridge is attached.

## 2. Write the listeners

A listener is a regular `ListenerInterface`. The Bitrix event data is in the payload, a `BitrixEvent` object:

```php
--8<-- "bitrix/bridge.php:listeners"
```

Bitrix calls handlers in two ways. `BitrixEvent` understands both:

| How Bitrix fired the event | How to read it | How to cancel |
| --- | --- | --- |
| old API: `GetModuleEvents()`, `&$arFields` | `getFields()`, `setField()`, `getArguments()` | `cancel($reason)` → Bitrix gets `false`, the reason is in `$APPLICATION->GetException()` |
| D7: `Bitrix\Main\Event::send()` | `getD7Event()` | `cancel($reason)` → Bitrix gets `EventResult::ERROR` with the reason in its parameters |

Check the Bitrix documentation of a specific event to see its style. If you mix them up, `getFields()` on a D7 event tells you so with an exception instead of returning an empty array.

## 3. Attach the bridge

In the project it is one call in `local/php_interface/include/events.php`:

```php
use Bitrix\Main\EventManager;
use Selyusize\EventsRouter\Bitrix\BitrixEventBridge;
use Selyusize\EventsRouter\Container\Container;
use Selyusize\EventsRouter\EventRouter;

BitrixEventBridge::attachLazy(
    EventManager::getInstance(),
    static fn (): EventRouter => Container::get(EventRouter::class),
    $_SERVER['DOCUMENT_ROOT'] . '/local/var/cache/bitrix-events.php',
);
```

`init.php` runs on every hit. Building the router, the container and all routes on every hit just to subscribe to a couple of events is expensive. So `attachLazy()`:

- takes the list of events from the file and subscribes only to them;
- builds the router only when a Bitrix event actually happens;
- if there is no file, builds the router right away and writes the list. The folder is created automatically.

**Delete the file after changing routes**, for example on deploy. Otherwise the bridge will not know about new events.

For console scripts and development there is `attach()`: it subscribes right away from the router's routes, without a file:

```php
--8<-- "bitrix/bridge.php:attach"
```

## What you get

Bitrix fires events as always; the bridge changes nothing in it:

```php
--8<-- "bitrix/bridge.php:fire"
```

```text
--8<-- "bitrix/bridge.out"
```

## If the module id has a dot

Partner module ids contain a dot: `rasa.shop`. And a dot separates topic segments. For such modules build the topic with `topic()`, which encodes the dot as `~`:

```php
$bitrix->listen(BitrixEventBridge::topic('rasa.shop', 'OnOrderExport'), ExportOrder::class);
// topic: bitrix.rasa~shop.OnOrderExport
```

## If the router has a prefix

The bridge looks for routes whose topic starts with `bitrix.`. If the router has `setPrefix('shop')`, the full topic becomes `shop.bitrix.main.OnAfterUserAdd`: pass the prefix to the bridge as the last argument, `attach($manager, $events, 'shop.bitrix')`.

## Keep in mind

- **A listener error does not break the page.** By default the exception goes to the report and the [router log](logging.md), other listeners run. An action can be cancelled only explicitly, with `cancel()`.
- **After `cancel()` the remaining listeners are not called.** `BitrixEvent` implements PSR-14 `StoppableEventInterface`; in the report they have the `Skipped` status.
- **`setField()` changes Bitrix data.** This is a deliberate exception to event immutability: the whole point of `OnBefore*` is to fix the fields. Changes made by one listener are seen by the next ones.
- **Your own events from Bitrix events.** A good technique is a listener that translates a Bitrix event into the domain language: it receives `sale.OnSaleOrderPaid` and dispatches your own `shop.order.42.paid`. Then the business logic does not know about Bitrix at all.
