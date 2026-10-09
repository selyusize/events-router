<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:listener]
use Selyusize\EventsRouter\Contract\Core\EventHandlerInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Event;

final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo 'Заказ ', $event->getAttribute('order_id'), ' оплачен', PHP_EOL;
    }
}
// --8<-- [end:listener]

// --8<-- [start:middleware]
final class EventLogger implements MiddlewareInterface
{
    public function process(EventInterface $event, EventHandlerInterface $next): void
    {
        echo '→ ', $event->getName(), PHP_EOL;

        $next->handle($event);

        echo '← ', $event->getName(), PHP_EOL;
    }
}
// --8<-- [end:middleware]

// --8<-- [start:pipeline]
// Так роутер соединяет middleware и слушателя. Вручную это писать не придётся.
$listener = new class implements EventHandlerInterface {
    public function handle(EventInterface $event): void
    {
        MarkOrderPaid::handle($event);
    }
};

(new EventLogger())->process(
    (new Event('shop.order.42.paid'))->withAttribute('order_id', '42'),
    $listener,
);
// --8<-- [end:pipeline]
