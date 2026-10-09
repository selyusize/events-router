<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Override;
use Selyusize\EventsRouter\Contract\Core\EventHandlerInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

/**
 * Последнее звено цепочки: вызов статичного слушателя. Запоминает, дошло ли до него событие.
 *
 * @internal
 */
final class ListenerCall implements EventHandlerInterface
{
    private bool $reached = false;

    /**
     * @param class-string<ListenerInterface> $listener
     */
    public function __construct(
        private readonly string $listener,
        private readonly HandlerResolver $resolver,
    ) {}

    #[Override]
    public function handle(EventInterface $event): void
    {
        $this->reached = true;
        $this->resolver->listener($this->listener)::handle($event);
    }

    public function isReached(): bool
    {
        return $this->reached;
    }
}
