<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Override;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Contract\Routing\RouteInterface;

/**
 * Маршрут: то, что возвращает `listen()`, — как Route в Slim.
 *
 * Настраивается цепочкой; при сборке таблицы превращается в CompiledRoute:
 *
 * ```php
 * $group->listen('{order_id}.paid', MarkOrderPaid::class)
 *     ->add(IdempotencyGuard::class);
 * ```
 */
final class Route implements RouteInterface
{
    /**
     * @var list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * @internal объявления создаёт группа
     *
     * @param string $pattern шаблон относительно префикса группы
     * @param class-string<ListenerInterface> $listener
     */
    public function __construct(
        private readonly string $pattern,
        private readonly string $listener,
        private readonly Revision $revision,
    ) {}

    #[Override]
    public function add(MiddlewareInterface|string $middleware): self
    {
        RouteAssert::middleware($middleware);

        $this->middleware[] = $middleware;
        $this->revision->bump();

        return $this;
    }

    /**
     * Шаблон относительно префикса группы, как передан в `listen()`.
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @return class-string<ListenerInterface>
     */
    public function getListener(): string
    {
        return $this->listener;
    }

    /**
     * Middleware маршрута в порядке выполнения: первым идёт добавленный последним.
     *
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return array_reverse($this->middleware);
    }
}
