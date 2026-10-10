<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\Contract\Exception\ExceptionInterface;

echo interface_exists(ExceptionInterface::class)
    ? 'events-router is installed' . PHP_EOL
    : 'events-router not found, check composer install' . PHP_EOL;
// --8<-- [end:example]
