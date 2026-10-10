# Core concepts

events-router is built like the Slim HTTP router. Only instead of a request there is an event, and instead of an Action there is a listener. Keep this analogy in mind, and the rest falls into place.

| events-router | Slim / PSR | What it is |
| --- | --- | --- |
| event, `EventInterface` | request, `ServerRequestInterface` | what happened: topic name, data, attributes |
| topic | URL path | event name made of dot-separated segments: `shop.order.42.paid` |
| listener, `ListenerInterface` | Action, `RequestHandlerInterface` | a reaction to the event |
| middleware, `MiddlewareInterface` | PSR-15 `MiddlewareInterface` | a wrapper around the listener |
| route | route | the "topic pattern → listener + middleware" link |

There is one main difference from HTTP: a request reaches exactly one Action, while an event can have **any number of listeners**. Almost everything that sets an event router apart from an HTTP router follows from this.

## Event {#event}

An event is a name, data and attributes.

```php
--8<-- "concepts/event.php:example"
```

**The name** is a concrete topic: what happened and to what. Not a pattern but a fact: `shop.order.42.paid`, not `shop.order.{order_id}.paid`.

**The payload** is the event data: an array, a DTO, any object. The router neither reads nor changes it.

**Attributes** are what the event collected on its way to the listener: parameters from the route pattern (`order_id` from `order.{order_id}.paid`) and data added by middleware.

**The event is immutable**, like a PSR-7 request. `withAttribute()` returns a copy. So each listener gets its own event with its own parameters and cannot spoil it for the others. The payload is not copied though: if it is an object, all copies point to the same object.

## Listener

One class, one reaction. The `handle()` method is static: the router calls `MarkOrderPaid::handle($event)` and creates no objects.

```php
--8<-- "concepts/listener-and-middleware.php:listener"
```

The listener takes its dependencies itself, from the [container](../how-to/container.md). Why it is done this way is in [Design decisions](design.md#static-listeners).

## Middleware

Middleware wraps a listener. It receives the event and a `$next` closure, the continuation of the chain: the next middleware or the listener itself. It can do something before and after, pass a modified event further, or not call `$next` at all; then the event never reaches the listener.

```php
--8<-- "concepts/listener-and-middleware.php:middleware"
```

The router assembles the chain of middleware and listener. From the inside it looks like this:

```php
--8<-- "concepts/listener-and-middleware.php:pipeline"
```

```text
--8<-- "concepts/listener-and-middleware.out"
```

## Route

A route links a topic pattern, one listener and its middleware. Routes are collected into groups with a shared prefix and shared middleware, and groups into one file.

Many routes can fire on one event. The router finds all of them and calls their listeners in turn, in the order the routes are written in the file. The syntax is in the [reference](../reference/routes.md).
