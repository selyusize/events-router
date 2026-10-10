# UnmappableEvent

`Selyusize\EventsRouter\Exception\UnmappableEvent`

`Psr14EventDispatcher::dispatch()` got an object whose event name is unknown: it does not implement `EventInterface`, and no mapper was given to the adapter.

## How to fix

Pass a mapper, a function that returns the event name for an object:

```php
<?php

$dispatcher = new Psr14EventDispatcher(
    $events,
    static fn (object $event): string => match (true) {
        $event instanceof OrderPaid => 'shop.order.' . $event->orderId . '.paid',
    },
);
```

Or dispatch objects implementing `EventInterface`, for example `Event`:

```php
$dispatcher->dispatch(new Event('shop.order.42.paid', new OrderPaid(42, 1500)));
```

If the mapper returns an invalid name, you get [`InvalidEventName`](invalid-event-name.md).
