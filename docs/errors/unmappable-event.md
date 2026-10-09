# UnmappableEvent

`Selyusize\EventsRouter\Exception\UnmappableEvent`

В `Psr14EventDispatcher::dispatch()` передан объект, для которого неизвестно имя события: он не реализует `EventInterface`, а маппер в адаптер не передан.

## Как исправить

Передайте маппер — функцию, которая возвращает имя события для объекта:

```php
<?php

$dispatcher = new Psr14EventDispatcher(
    $events,
    static fn (object $event): string => match (true) {
        $event instanceof OrderPaid => 'shop.order.' . $event->orderId . '.paid',
    },
);
```

Или рассылайте объекты, реализующие `EventInterface`, например `Event`:

```php
$dispatcher->dispatch(new Event('shop.order.42.paid', new OrderPaid(42, 1500)));
```

Если маппер вернёт некорректное имя, будет [`InvalidEventName`](invalid-event-name.md).
