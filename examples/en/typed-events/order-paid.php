<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// --8<-- [start:dto]
use Selyusize\EventsRouter\Event;

final class OrderPaid
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $amount,
    ) {}

    /**
     * @return Event<self>
     */
    public static function event(int $orderId, int $amount): Event
    {
        return new Event('shop.order.' . $orderId . '.paid', new self($orderId, $amount));
    }
}
// --8<-- [end:dto]

// --8<-- [start:listener]
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class SendReceipt implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        $order = $event->getPayload();
        assert($order instanceof OrderPaid);

        echo 'Receipt for order ', $order->orderId, ': $', $order->amount, PHP_EOL;
    }
}
// --8<-- [end:listener]

// --8<-- [start:dispatch]
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create();
$events->listen('shop.order.{order_id}.paid', SendReceipt::class);

$events->dispatch(OrderPaid::event(42, 1500));
// --8<-- [end:dispatch]
