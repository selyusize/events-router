<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create();
$events->loadRoutes(require __DIR__ . '/events.php');

foreach (['shop.order.created', 'shop.order.42.paid', 'shop.order.42.payment.failed', 'shop.catalog.updated'] as $topic) {
    echo $topic, PHP_EOL;

    $matches = $events->match($topic);

    if ($matches === []) {
        echo '    no listeners', PHP_EOL;
    }

    foreach ($matches as $i => $match) {
        printf(
            "    %d. %s %s\n",
            $i + 1,
            substr(strrchr('\\' . $match->getRoute()->getListener(), '\\'), 1),
            json_encode($match->getParameters(), JSON_FORCE_OBJECT),
        );
    }
}
// --8<-- [end:example]
