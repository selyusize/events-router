<?php

declare(strict_types=1);

// --8<-- [start:example]
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\Routing\RouteGroup;

return static function (EventRouter $events): void {
    $events->setPrefix('shop');

    $events->group('order', static function (RouteGroup $group): void {
        $group->listen('{order_id}.paid', MarkOrderPaid::class);
    });
};
// --8<-- [end:example]
