<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Topic\TopicPattern;

try {
    TopicPattern::fromString('order-{order_id}.paid');
} catch (InvalidTopicPattern $error) {
    echo $error->getMessage(), PHP_EOL;
}
// --8<-- [end:example]
