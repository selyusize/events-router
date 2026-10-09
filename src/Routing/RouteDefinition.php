<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

/**
 * Объявление маршрута: то, что возвращает `listen()`.
 *
 * Настраивается цепочкой и превращается в готовый Route при сборке таблицы:
 *
 * ```php
 * $group->listen('{order_id}.paid', MarkOrderPaid::class)
 *     ->add(IdempotencyGuard::class)
 *     ->name('order.mark_paid');
 * ```
 */
final class RouteDefinition
{
    /**
     * @var list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * @var non-empty-string|null
     */
    private ?string $name = null;

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

    /**
     * Добавить middleware только этому маршруту.
     *
     * Добавленный последним выполняется первым, как в Slim.
     * Middleware групп выполняются раньше middleware маршрута.
     *
     * @param class-string<MiddlewareInterface>|MiddlewareInterface $middleware
     */
    public function add(MiddlewareInterface|string $middleware): self
    {
        $this->middleware[] = $middleware;
        $this->revision->bump();

        return $this;
    }

    /**
     * Имя маршрута — для отладки, логов и, позже, асинхронной очереди.
     *
     * @param non-empty-string $name
     */
    public function name(string $name): self
    {
        $this->name = $name;
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

    /**
     * @return non-empty-string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }
}
