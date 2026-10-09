<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:classes]
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo '    MarkOrderPaid: заказ ', $event->getAttribute('order_id'), PHP_EOL;
    }
}

final class AccrueBonuses implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        throw new RuntimeException('сервис бонусов недоступен');
    }
}

final class SendReceipt implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo '    SendReceipt: чек на ', $event->getPayload()['amount'], ' ₽', PHP_EOL;
    }
}

final class EventLogger implements MiddlewareInterface
{
    public function process(EventInterface $event, Closure $next): void
    {
        echo '→ ', $event->getName(), PHP_EOL;
        $next($event);
        echo '← ', $event->getName(), PHP_EOL;
    }
}
// --8<-- [end:classes]

// --8<-- [start:dispatch]
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Routing\RouteGroup;

$events = EventRouterFactory::create();   // или EventRouterFactory::create($container)
$events->add(EventLogger::class);

$events->group('order', static function (RouteGroup $group): void {
    $group->listen('{order_id}.paid', MarkOrderPaid::class);
    $group->listen('{order_id}.paid', AccrueBonuses::class);
    $group->listen('{order_id}.paid', SendReceipt::class);
});

$report = $events->dispatch(new Event('order.42.paid', ['amount' => 1500]));

foreach ($report->getListeners() as $listener) {
    echo $listener->getListener(), ': ', $listener->getStatus()->name;
    echo $listener->isFailed() ? ' — ' . $listener->getError()?->getMessage() : '', PHP_EOL;
}
// --8<-- [end:dispatch]
