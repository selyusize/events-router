<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

use Selyusize\EventsRouter\Contract\Core\EventHandlerInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

final class RecordingMiddleware implements MiddlewareInterface
{
    public static int $instances = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public function process(EventInterface $event, EventHandlerInterface $next): void
    {
        Journal::write('RecordingMiddleware');
        $next->handle($event);
    }
}
