<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Override;
use Selyusize\EventsRouter\Contract\Core\EventHandlerInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

/**
 * Звено цепочки: middleware и следующее за ним звено.
 *
 * Middleware достаётся из резолвера в момент вызова, поэтому ошибка его создания
 * относится к той цепочке, в которой он стоит.
 *
 * @internal
 */
final class Pipeline implements EventHandlerInterface
{
    /**
     * @param class-string<MiddlewareInterface>|MiddlewareInterface $middleware
     */
    private function __construct(
        private readonly MiddlewareInterface|string $middleware,
        private readonly EventHandlerInterface $next,
        private readonly HandlerResolver $resolver,
    ) {}

    /**
     * Обернуть звено в middleware. Первый в списке выполняется первым.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     */
    public static function wrap(array $middleware, EventHandlerInterface $last, HandlerResolver $resolver): EventHandlerInterface
    {
        $next = $last;

        foreach (array_reverse($middleware) as $item) {
            $next = new self($item, $next, $resolver);
        }

        return $next;
    }

    #[Override]
    public function handle(EventInterface $event): void
    {
        $this->resolver->middleware($this->middleware)->process($event, $this->next);
    }
}
