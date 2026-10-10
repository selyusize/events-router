<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\Event;

$event = new Event('shop.order.42.paid', ['amount' => 1500]);

echo $event->getName(), PHP_EOL;                         // shop.order.42.paid
echo $event->getPayload()['amount'], PHP_EOL;            // 1500

// The event is immutable: withAttribute() returns a copy
$withId = $event->withAttribute('order_id', '42');

echo $withId->getAttribute('order_id'), PHP_EOL;          // 42
echo $event->getAttribute('order_id', 'none'), PHP_EOL;   // none — the original event is unchanged
// --8<-- [end:example]
