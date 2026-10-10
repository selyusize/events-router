# Coming from Slim

If you have written routes in Slim, you already know how to write event routes. There are few differences, and almost all of them follow from one fact: an event can have several listeners.

| Slim | events-router | Comment |
| --- | --- | --- |
| `return static function (App $app): void { ... }` | `return static function (EventRouter $events): void { ... }` | the routes file is the same kind of closure |
| `$app->setBasePath('/api/v1')` | `$events->setPrefix('shop')` | common prefix for all topics |
| `$app->post('/token/refresh', Action::class)` | `$events->listen('token.refreshed', Listener::class)` | one method instead of HTTP verbs |
| `$app->group('/auth', function (RouteCollectorProxy $group) { ... })` | `$events->group('auth', static function (RouteGroup $group): void { ... })` | segments are joined with a dot, not `/` |
| `$group->group('/certificates', ...)` | `$group->group('certificates', ...)` | nesting of any depth |
| `$app->group('', ...)->add(Auth::class)` | `$events->group('', ...)->add(Auth::class)` | a group without a prefix, just for shared middleware |
| `/order` in two groups with different middleware | `order` in two groups with different middleware | works here too |
| `/{suborderId}/deliveries` | `{order_id}.delivered`, `{id:\d+}` | parameters become event attributes, names are snake_case |
| — | `*`, `#` | one segment / any number of segments |
| `->add(A::class)->add(B::class)` | the same | B runs before A |
| Action, `RequestHandlerInterface` | listener, `ListenerInterface` | `public static function handle(EventInterface $event): void`, static |
| PSR-15 middleware | `MiddlewareInterface` | `process(EventInterface $event, Closure $next): void`, then `$next($event)` |
| `$app->run()` | `$events->run($source)` | see [Run a worker](workers.md) |

## The main difference

An HTTP request reaches exactly **one** Action, the first matching route. An event is received by **all** matching listeners, in the order routes are declared. Hence:

- one event can be listened to in different places of the file: `listen('order.created', A)` and `listen('order.created', B)`;
- the patterns `order.{order_id}.paid` and `order.#` do not conflict: the event `order.42.paid` reaches both listeners.

## What Slim has and this does not

- **A parameter inside a segment** (`/order-{id}`). A parameter takes the whole segment. If a segment needs parsing, use a regex: `{order_ref:order-\d+}`. More in [Topic patterns](../reference/topics.md#limits).
- **Optional parts** (`[/{id}]`). Use `#` instead.
