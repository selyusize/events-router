<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Closure;
use Override;
use Selyusize\EventsRouter\Contract\Core\EventHandlerInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;

/**
 * Звено цепочки из замыкания: последнее звено после middleware роутера.
 *
 * @internal
 */
final class CallbackHandler implements EventHandlerInterface
{
    /**
     * @param Closure(EventInterface): void $callback
     */
    public function __construct(
        private readonly Closure $callback,
    ) {}

    #[Override]
    public function handle(EventInterface $event): void
    {
        ($this->callback)($event);
    }
}
