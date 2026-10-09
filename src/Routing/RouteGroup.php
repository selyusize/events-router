<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Override;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Contract\Routing\RouteCollectorInterface;

/**
 * Группа маршрутов: общий префикс топика и общие middleware.
 *
 * Аналог `RouteCollectorProxy` в Slim. Middleware группы оборачивают
 * middleware каждого её маршрута, внешняя группа оборачивает вложенную.
 * `add()` можно вызвать после объявления маршрутов — так обычно и пишут:
 *
 * ```php
 * $events->group('order', static function (RouteGroup $group): void {
 *     $group->listen('created', SendConfirmationEmail::class);
 *     $group->listen('{order_id}.paid', MarkOrderPaid::class);
 * })
 *     ->add(SetUserData::class)
 *     ->add(EventLogger::class);   // выполнится первым
 * ```
 */
final class RouteGroup implements RouteCollectorInterface
{
    /**
     * @var list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * @internal группы создаёт роутер или родительская группа
     */
    public function __construct(
        private readonly RouteCollector $collector,
        private readonly string $prefix,
        private readonly ?self $parent = null,
    ) {}

    #[Override]
    public function listen(string $pattern, ListenerInterface|string $listener): Route
    {
        return $this->collector->addRoute($this->path($pattern), $listener, $this);
    }

    #[Override]
    public function group(string $prefix, callable $routes): self
    {
        $group = new self($this->collector, $prefix, $this);
        $routes($group);

        return $group;
    }

    /**
     * Добавить middleware всем маршрутам группы, включая вложенные группы.
     *
     * Добавленный последним выполняется первым, как в Slim.
     *
     * @param class-string<MiddlewareInterface>|MiddlewareInterface $middleware
     */
    public function add(MiddlewareInterface|string $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * Префикс группы, как он передан в `group()`.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Middleware этой группы в порядке выполнения: первым идёт добавленный последним.
     * Middleware родительских групп сюда не входят.
     *
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return array_reverse($this->middleware);
    }

    /**
     * Цепочка групп от самой внешней до этой.
     *
     * @return non-empty-list<self>
     */
    public function getGroups(): array
    {
        return $this->parent === null ? [$this] : [...$this->parent->getGroups(), $this];
    }

    private function path(string $pattern): string
    {
        return $this->parent === null
            ? RouteCollector::join($this->prefix, $pattern)
            : $this->parent->path(RouteCollector::join($this->prefix, $pattern));
    }
}
