<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

use Closure;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

final class RecordingMiddleware implements MiddlewareInterface
{
    public function process(EventInterface $event, Closure $next): void
    {
        Journal::write('RecordingMiddleware');
        $next($event);
    }
}
