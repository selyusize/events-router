<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create();
$events->loadRoutes(require __DIR__ . '/events.php');

$short = static fn (object|string $class): string => substr(strrchr('\\' . (is_string($class) ? $class : $class::class), '\\'), 1);

foreach ($events->getRoutes() as $route) {
    printf(
        "%-38s %-22s %s\n",
        $route->getPattern()->getPattern(),
        $short($route->getListener()),
        implode(' → ', array_map($short, $route->getMiddleware())),
    );
}
// --8<-- [end:example]
