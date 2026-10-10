<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\Topic\TopicPattern;

$cases = [
    ['order.{order_id}.paid', 'order.42.paid'],
    ['order.{order_id:\d+}.paid', 'order.abc.paid'],
    ['order.*.cancelled', 'order.42.cancelled'],
    ['order.*.cancelled', 'order.42.items.cancelled'],
    ['order.#', 'order'],
    ['order.#', 'order.42.payment.failed'],
    ['order.{order_id}.payment.#', 'order.42.payment.failed'],
];

foreach ($cases as [$pattern, $topic]) {
    $params = TopicPattern::fromString($pattern)->match($topic);

    echo str_pad($pattern, 28), str_pad($topic, 26), match (true) {
        $params === null => 'no',
        $params === [] => 'yes',
        default => 'yes, ' . json_encode($params),
    }, PHP_EOL;
}
// --8<-- [end:example]
