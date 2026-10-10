<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:listener]
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo 'Заказ ', $event->getAttribute('order_id'), ' оплачен на ', $event->getPayload()['amount'], ' ₽', PHP_EOL;
    }
}
// --8<-- [end:listener]

$container = null;

// --8<-- [start:dispatch]
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create(container: $container);   // любой PSR-11 или null — тогда PHP-DI
(require __DIR__ . '/events.php')($events);

$report = $events->dispatch(new Event('shop.order.42.paid', ['amount' => 1500]));

echo $report->hasFailures() ? 'есть ошибки' : 'без ошибок', PHP_EOL;
// --8<-- [end:dispatch]
