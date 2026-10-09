# events-router

Роутер событий для PHP. Маршруты событий описываются так же, как HTTP-маршруты в Slim: шаблоны топиков с параметрами, группы, middleware на группах и маршрутах. На одно событие можно повесить несколько слушателей. Совместим с PSR-14.

!!! note "Версия 0.1"
    До 1.0 API может меняться в минорных версиях. Что изменилось — в [списке изменений](changelog.md).

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

## Чем отличается от других диспетчеров событий

| | `symfony/event-dispatcher` | `symfony/messenger` | **events-router** |
| --- | --- | --- | --- |
| Как выбирается слушатель | точное имя события, порядок — приоритет | класс сообщения → шина/транспорт | **шаблон топика** (`order.{id}.paid`, `order.*`) |
| Middleware | нет | общие на всю шину | **на маршрут и на группу, с наследованием** |
| Группы, префиксы | нет | нет | **есть, как в Slim** |
| Где описаны маршруты | атрибуты и подписчики по всему коду | конфиг маршрутизации | **один декларативный файл** |

## С чего начать

- [Быстрый старт](guide/getting-started.md) — установка и проверка.
- [Основные понятия](guide/concepts.md) — событие, слушатель, middleware.
- [Топики и шаблоны](guide/topics.md) — `order.{order_id}.paid`, `*`, `#`.
- [Маршруты](guide/routes.md) — `listen()`, `group()`, `add()`, порядок middleware.
- [Рассылка](guide/dispatching.md) — `dispatch()`, порядок вызова, отчёт, middleware из контейнера.
- [Обработка ошибок](guide/errors.md) — обработчик, стратегии, PSR-14 остановка.
- [Воркеры](guide/workers.md) — `run()` и источники событий.
- [Типизированные события](guide/typed-events.md) — DTO в payload.
- [PSR-14](guide/psr14.md) — роутер под стандартным интерфейсом.
- [Переход со Slim](guide/from-slim.md) — что так же, а что иначе.
- [Справочник API](api/index.md) — все публичные классы и методы, собирается из кода.
- [Ошибки](errors/index.md) — что означает каждое исключение библиотеки и как его исправить.
