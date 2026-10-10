<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

use Closure;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

final class MarkingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Mark $mark,
    ) {}

    public function process(EventInterface $event, Closure $next): void
    {
        Journal::write('MarkingMiddleware ' . $this->mark->text());
        $next($event);
    }
}
