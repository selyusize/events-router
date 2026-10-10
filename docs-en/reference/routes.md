# Routes

Routes live in a separate file. The file returns a function that receives the router, just like `routes.php` in Slim.

```php
--8<-- "routes/events.php:example"
```

The resulting list of routes with full patterns and middleware:

```php
--8<-- "routes/dump-routes.php:example"
```

```text
--8<-- "routes/dump-routes.out"
```

## Methods

| Method | Available on | What it does |
| --- | --- | --- |
| `loadRoutes(callable $routes)` | router | loads the routes file; with `route_cache_file`, through the cache |
| `listen(string $pattern, string $listener)` | router, group | attaches **one** listener to a pattern, returns the route |
| `group(string $prefix, callable $routes)` | router, group | a group with a shared prefix and middleware, returns the group |
| `add($middleware)` | router, group, route | adds middleware |
| `setPrefix(string $prefix)` | router | common prefix of all topics, like `setBasePath()` |
| `match(string $topic)` | router | which routes will receive the event, with parameters |
| `getRoutes()` | router | all routes with full patterns and middleware |

## `loadRoutes()`

```php
$events->loadRoutes(require __DIR__ . '/events-routes.php');
```

Calls the function from the routes file, passing it the router. Without the cache it is the same as `(require ...)($events)`. With the `route_cache_file` key and an existing cache file, the function is not called: the table comes from the cache. The rules for the cache mode are in [Enable the route cache](../how-to/cache.md).

## `listen()`

```php
$group->listen('{order_id}.paid', Listener\Order\MarkOrderPaid::class);
```

- The first argument is a [topic pattern](topics.md) relative to the group prefix. Inside a group it can be empty: `listen('', ...)` listens to the event named by the group prefix itself.
- The second is **exactly one** listener: the name of a class implementing `ListenerInterface`. No arrays, no objects.
- Several listeners on one event means several `listen()` lines.

## Call order

Listeners of one event are called **strictly in the order routes are declared**, across all groups. There are no priorities. To make a listener run earlier, declare it higher.

```php
$order->listen('created', Listener\Order\ReserveStock::class);           // first
$order->listen('created', Listener\Order\SendConfirmationEmail::class);  // second
```

## `group()`

```php
$events->group('order', static function (RouteGroup $group): void {
    // routes with the order prefix
})
    ->add(Middleware\EventLog\EventLogger::class);
```

- The prefix is joined with patterns by a dot. Groups nest to any depth, and a prefix can be a parameter: `group('{order_id}', ...)`.
- **An empty prefix**, `group('', ...)`, is a group just for shared middleware, like `$app->group('', ...)` in Slim.
- **One prefix in several groups** with different middleware is allowed; the routes do not mix.

## `add()`

Middleware is given as a class name or a ready object. For a class name, the object is created by the [container](../how-to/container.md) when an event reaches that middleware.

Execution order, as in Slim:

1. router → outer group → nested group → route;
2. on the same level **the last one added runs first**.

```php
--8<-- "routes/middleware-order.php:example"
```

```text
--8<-- "routes/middleware-order.out"
```

`add()` on a group can be called after the routes inside are declared: the middleware applies to all routes of the group.

Router middleware (`$events->add()`) runs **once per event** and wraps all listeners at once. More in [Dispatch](dispatch.md).

## `setPrefix()`

The common prefix of all topics. It can be called at any time: patterns of already declared routes are rebuilt. An empty string removes the prefix. If some pattern becomes invalid with the new prefix, the router throws and keeps the old prefix.

## `match()` {#match}

Which routes fire on an event: **all** matching ones, strictly in declaration order, each with its own parameters.

```php
--8<-- "routes/match.php:example"
```

```text
--8<-- "routes/match.out"
```

## When errors are checked

Everything is checked at declaration, not when an event arrives. A mistake in the routes file shows up on the first application load, and the stack trace points to its line.

| What | When | Exception |
| --- | --- | --- |
| the pattern together with group and router prefixes | `listen()`, `setPrefix()` | [`InvalidTopicPattern`](../errors/invalid-topic-pattern.md) |
| the listener class exists and implements `ListenerInterface` | `listen()` | [`InvalidRoute`](../errors/invalid-route.md) |
| the middleware class exists and implements `MiddlewareInterface` | `add()` | [`InvalidRoute`](../errors/invalid-route.md) |
| with the cache: a route declared outside `loadRoutes()`, a second `loadRoutes()` call, a middleware object | `listen()`, `group()`, `setPrefix()`, `loadRoutes()` | [`InvalidRoute`](../errors/invalid-route.md) |
