<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// --8<-- [start:listener]
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Event;

final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo 'Order ', $event->getAttribute('order_id'), ' paid', PHP_EOL;
    }
}
// --8<-- [end:listener]

// --8<-- [start:middleware]
final class EventLogger implements MiddlewareInterface
{
    public function process(EventInterface $event, Closure $next): void
    {
        echo '→ ', $event->getName(), PHP_EOL;

        $next($event);

        echo '← ', $event->getName(), PHP_EOL;
    }
}
// --8<-- [end:middleware]

// --8<-- [start:pipeline]
// This is how the router joins middleware and listener. You never write this by hand.
(new EventLogger())->process(
    (new Event('shop.order.42.paid'))->withAttribute('order_id', '42'),
    MarkOrderPaid::handle(...),
);
// --8<-- [end:pipeline]
