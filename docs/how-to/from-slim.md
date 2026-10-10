# Перейти со Slim

Если вы писали маршруты в Slim, маршруты событий вы уже умеете писать. Отличий немного, и почти все они следуют из одного факта: у события может быть несколько слушателей.

| Slim | events-router | Комментарий |
| --- | --- | --- |
| `return static function (App $app): void { ... }` | `return static function (EventRouter $events): void { ... }` | файл маршрутов — такое же замыкание |
| `$app->setBasePath('/api/v1')` | `$events->setPrefix('shop')` | общий префикс всех топиков |
| `$app->post('/token/refresh', Action::class)` | `$events->listen('token.refreshed', Listener::class)` | один метод вместо HTTP-глаголов |
| `$app->group('/auth', function (RouteCollectorProxy $group) { ... })` | `$events->group('auth', static function (RouteGroup $group): void { ... })` | сегменты соединяются точкой, а не `/` |
| `$group->group('/certificates', ...)` | `$group->group('certificates', ...)` | вложенность любой глубины |
| `$app->group('', ...)->add(Auth::class)` | `$events->group('', ...)->add(Auth::class)` | группа без префикса ради общих middleware |
| `/order` в двух группах с разными middleware | `order` в двух группах с разными middleware | так тоже можно |
| `/{suborderId}/deliveries` | `{order_id}.delivered`, `{id:\d+}` | параметры попадают в атрибуты события, имена — snake_case |
| — | `*`, `#` | один сегмент / любое число сегментов |
| `->add(A::class)->add(B::class)` | то же | B выполняется раньше A |
| Action, `RequestHandlerInterface` | слушатель, `ListenerInterface` | `public static function handle(EventInterface $event): void` — статичный |
| PSR-15 middleware | `MiddlewareInterface` | `process(EventInterface $event, Closure $next): void`, дальше — `$next($event)` |
| `$app->run()` | `$events->run($source)` | см. [Запустить воркер](workers.md) |

## Главное отличие

HTTP-запрос попадает ровно в **один** Action — в первый подходящий маршрут. Событие получают **все** подходящие слушатели, в порядке объявления маршрутов. Отсюда:

- одно событие можно слушать в разных местах файла: `listen('order.created', A)` и `listen('order.created', B)`;
- шаблоны `order.{order_id}.paid` и `order.#` не конфликтуют: событие `order.42.paid` получат оба слушателя.

## Чего в Slim есть, а здесь нет

- **Параметра внутри сегмента** (`/order-{id}`). Параметр занимает сегмент целиком. Если сегмент нужно разобрать, используйте regex: `{order_ref:order-\d+}`. Подробнее — в [Шаблонах топиков](../reference/topics.md#limits).
- **Необязательных частей** (`[/{id}]`). Вместо них — `#`.
