<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo '    MarkOrderPaid: order ', $event->getAttribute('order_id'), PHP_EOL;
    }
}

$cacheFile = sys_get_temp_dir() . '/events-router-example-' . bin2hex(random_bytes(4)) . '/events-routes.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\EventRouterFactory;

// The routes file, as usual, is a function that receives the router
$routes = static function (EventRouter $events): void {
    echo '    the routes file runs', PHP_EOL;

    $events->setPrefix('shop');
    $events->listen('order.{order_id}.paid', MarkOrderPaid::class);
};

foreach (['first run', 'second run'] as $run) {
    echo $run, ':', PHP_EOL;

    $events = EventRouterFactory::create(config: ['route_cache_file' => $cacheFile]);
    $events->loadRoutes($routes);

    $events->dispatch(new Event('shop.order.42.paid'));
}
// --8<-- [end:example]

exec('rm -rf ' . escapeshellarg(dirname($cacheFile)));
