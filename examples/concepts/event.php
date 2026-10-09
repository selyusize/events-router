<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\Event;

$event = new Event('shop.order.42.paid', ['amount' => 1500]);

echo $event->getName(), PHP_EOL;                         // shop.order.42.paid
echo $event->getPayload()['amount'], PHP_EOL;            // 1500

// Событие неизменяемо: withAttribute() возвращает копию
$withId = $event->withAttribute('orderId', '42');

echo $withId->getAttribute('orderId'), PHP_EOL;          // 42
echo $event->getAttribute('orderId', 'нет'), PHP_EOL;    // нет — исходное событие не изменилось
// --8<-- [end:example]
