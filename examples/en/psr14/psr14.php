<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class OrderPaid
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $amount,
    ) {}
}

final class SendReceipt implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        $order = $event->getPayload();
        assert($order instanceof OrderPaid);

        echo 'Receipt for order ', $order->orderId, ': $', $order->amount, PHP_EOL;
    }
}

// --8<-- [start:example]
use Psr\EventDispatcher\EventDispatcherInterface;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Psr14\Psr14EventDispatcher;

$events = EventRouterFactory::create();
$events->listen('shop.order.{order_id}.paid', SendReceipt::class);

$dispatcher = new Psr14EventDispatcher(
    $events,
    static fn (object $event): string => match (true) {
        $event instanceof OrderPaid => 'shop.order.' . $event->orderId . '.paid',
    },
);

// From here on, the code knows only about PSR-14
function pay(EventDispatcherInterface $dispatcher): void
{
    $dispatcher->dispatch(new OrderPaid(42, 1500));
}

pay($dispatcher);
// --8<-- [end:example]
