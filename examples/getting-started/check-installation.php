<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:example]
use Selyusize\EventsRouter\Exception\ExceptionInterface;

echo interface_exists(ExceptionInterface::class)
    ? 'events-router установлен' . PHP_EOL
    : 'events-router не найден, проверьте composer install' . PHP_EOL;
// --8<-- [end:example]
