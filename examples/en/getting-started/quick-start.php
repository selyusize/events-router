<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

// --8<-- [start:listener]
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo 'Order ', $event->getAttribute('order_id'), ' paid: $', $event->getPayload()['amount'], PHP_EOL;
    }
}
// --8<-- [end:listener]

$container = null;

// --8<-- [start:dispatch]
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create(
    container: $container,           // any PSR-11 container, or null for PHP-DI
    config: ['locale' => 'en'],      // exceptions and log in English
);
$events->loadRoutes(require __DIR__ . '/events.php');

$report = $events->dispatch(new Event('shop.order.42.paid', ['amount' => 1500]));

echo $report->hasFailures() ? 'some listeners failed' : 'no failures', PHP_EOL;
// --8<-- [end:dispatch]
