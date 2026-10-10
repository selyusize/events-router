<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

// Роутер проверяет классы в listen() и add(), поэтому они объявлены, хоть и пустые
final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void {}
}

abstract class PassThrough implements MiddlewareInterface
{
    final public function process(EventInterface $event, Closure $next): void
    {
        $next($event);
    }
}

final class OuterA extends PassThrough {}
final class OuterB extends PassThrough {}
final class InnerA extends PassThrough {}
final class InnerB extends PassThrough {}
final class RouteA extends PassThrough {}
final class RouteB extends PassThrough {}

// --8<-- [start:example]
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Routing\RouteGroup;

$events = EventRouterFactory::create();

$events->group('order', static function (RouteGroup $order): void {
    $order->group('{order_id}', static function (RouteGroup $one): void {
        $one->listen('paid', MarkOrderPaid::class)
            ->add(RouteA::class)
            ->add(RouteB::class);
    })
        ->add(InnerA::class)
        ->add(InnerB::class);
})
    ->add(OuterA::class)
    ->add(OuterB::class);

echo implode(' → ', $events->getRoutes()[0]->getMiddleware()), ' → MarkOrderPaid', PHP_EOL;
// --8<-- [end:example]
