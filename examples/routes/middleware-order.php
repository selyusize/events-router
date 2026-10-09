<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Routing\RouteGroup;

$events = EventRouterFactory::create();

$events->group('order', static function (RouteGroup $order): void {
    $order->group('{order_id}', static function (RouteGroup $one): void {
        $one->listen('paid', 'MarkOrderPaid')
            ->add('RouteA')
            ->add('RouteB');
    })
        ->add('InnerA')
        ->add('InnerB');
})
    ->add('OuterA')
    ->add('OuterB');

echo implode(' → ', $events->getRoutes()[0]->getMiddleware()), ' → MarkOrderPaid', PHP_EOL;
// --8<-- [end:example]
