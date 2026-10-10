<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

use Closure;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

/**
 * Middleware, который просто передаёт событие дальше: основа для классов в routing-classes.php.
 */
abstract class StubMiddleware implements MiddlewareInterface
{
    final public function process(EventInterface $event, Closure $next): void
    {
        $next($event);
    }
}
