# events-router

Роутер событий для PHP. Маршруты событий описываются так же, как HTTP-маршруты в Slim: шаблоны топиков с параметрами, группы, middleware на группах и маршрутах. На одно событие можно повесить несколько слушателей. Совместим с PSR-14.

!!! warning "Библиотека в разработке"
    API ещё не стабилен. Код ниже показывает, к какому API мы идём; пока он не реализован, на сайте нет его исполняемых примеров. Ход работ — в [плане](https://github.com/selyusize/events-router/blob/main/plan.md).

```php
<?php

return static function (EventRouter $events): void {

    $events->setPrefix('shop');

    $events->group('order', static function (RouteGroup $group): void {
        $group->listen('created', Listener\Order\SendConfirmationEmail::class);
        $group->listen('created', Listener\Order\ReserveStock::class)->priority(100);

        $group->listen('{orderId}.paid', [
            Listener\Order\MarkOrderPaid::class,
            Listener\Bonuses\AccrueBonuses::class,
        ])->add(Middleware\Idempotency\IdempotencyGuard::class);
    })
        ->add(Middleware\EventLog\EventLogger::class);
};
```

## Чем отличается от других диспетчеров событий

| | `symfony/event-dispatcher` | `symfony/messenger` | **events-router** |
| --- | --- | --- | --- |
| Как выбирается слушатель | точное имя события + приоритет | класс сообщения → шина/транспорт | **шаблон топика** (`order.{id}.paid`, `order.*`) |
| Middleware | нет | общие на всю шину | **на маршрут и на группу, с наследованием** |
| Группы, префиксы | нет | нет | **есть, как в Slim** |
| Где описаны маршруты | атрибуты и подписчики по всему коду | конфиг маршрутизации | **один декларативный файл** |

## С чего начать

- [Быстрый старт](guide/getting-started.md) — установка и проверка.
- [Справочник API](api/index.md) — все публичные классы и методы, собирается из кода.
- [Ошибки](errors/index.md) — что означает каждое исключение библиотеки и как его исправить.
