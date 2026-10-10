# Enable the route cache

The routes file runs on every application start. And each `listen()` does more than remember a line: it parses the pattern, compiles it into a regular expression and loads the listener class to make sure it exists. For twenty routes this is unnoticeable. For two hundred it is two hundred files the autoloader opens on every request, even if not a single event happens.

The cache removes this work. The route table is built once and saved to a PHP file. After that the router takes it from there, and the routes file does not run at all.

## How to enable it

Two steps. The cache file path goes into the factory config, the routes go through `loadRoutes()`:

```php
--8<-- "cache/route-cache.php:example"
```

```text
--8<-- "cache/route-cache.out"
```

On the first run there is no cache file: the router runs the routes file and writes the table. On the second run the routes file is no longer needed; everything comes from the cache.

With the cache a router with 200 listeners is ready in 2.4 µs instead of 643 µs, and a PHP-FPM request that builds the router and dispatches three events takes about 21 µs: 1.8 times faster than `symfony/event-dispatcher` and 60 times faster than without the cache. Listener classes are not loaded from the cache at all, only when an event reaches the listener. Details and how it was measured are in [Performance](../explanation/performance.md).

## Where to enable it

Only in production. In development routes change all the time, and the cache does not refresh itself: a forgotten cache file means a route that is "definitely added" but never fires.

```php
$events = EventRouterFactory::create($container, [
    'route_cache_file' => $isProd ? __DIR__ . '/var/cache/events-routes.php' : null,
]);
$events->loadRoutes(require __DIR__ . '/events-routes.php');
```

**Delete the cache file on deploy.** A new one is built on the first request. If OPcache in production does not check file modification times (`opcache.validate_timestamps=0`), reset it too, as you do after a deploy anyway.

## Rules that come with the cache

The cache works on an all-or-nothing basis. So that production and development behave the same, the router watches three things and throws [`InvalidRoute`](../errors/invalid-route.md) immediately instead of waiting for the difference to surface in production:

- **Routes are declared only inside `loadRoutes()`.** `listen()`, `group()` and `setPrefix()` outside it are an error. Such a route would simply be missing from the cache.
- **`loadRoutes()` is called once.** All routes come from one file.
- **Middleware only by class name.** An object cannot be written to a PHP file: `->add(IdempotencyGuard::class)` instead of `->add(new IdempotencyGuard(...))`. The [container](container.md) injects the dependencies of such middleware.

Router middleware (`$events->add()`) added inside the routes file is cached too. Middleware added outside, before or after `loadRoutes()`, works as usual and is not written to the cache.

## If the cache file is broken

The router does not use a damaged file or a file from an old library version: it builds the routes again and overwrites the cache. If writing fails (no permissions, no space), the routes still work, and PHP gets an `E_USER_WARNING`.
