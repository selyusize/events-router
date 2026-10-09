<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

use Override;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Routing\RouteCollectorInterface;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Routing\Route;
use Selyusize\EventsRouter\Routing\RouteCollector;
use Selyusize\EventsRouter\Routing\RouteGroup;

/**
 * Роутер событий — аналог `Slim\App`.
 *
 * Маршруты описываются в отдельном файле, так же как HTTP-маршруты в Slim:
 *
 * ```php
 * return static function (EventRouter $events): void {
 *     $events->setPrefix('shop');
 *
 *     $events->group('order', static function (RouteGroup $group): void {
 *         $group->listen('created', ReserveStock::class);
 *         $group->listen('created', SendConfirmationEmail::class);
 *         $group->listen('{order_id}.paid', MarkOrderPaid::class);
 *     })
 *         ->add(EventLogger::class);
 * };
 * ```
 */
final class EventRouter implements RouteCollectorInterface
{
    private readonly RouteCollector $collector;

    public function __construct()
    {
        $this->collector = new RouteCollector();
    }

    /**
     * Общий префикс всех топиков — аналог `setBasePath()` в Slim.
     *
     * Можно вызвать в любой момент: шаблоны уже объявленных маршрутов пересобираются.
     * Пустая строка убирает префикс.
     *
     * @throws InvalidTopicPattern если с новым префиксом какой-то шаблон станет некорректным
     */
    public function setPrefix(string $prefix): self
    {
        $this->collector->setPrefix($prefix);

        return $this;
    }

    public function getPrefix(): string
    {
        return $this->collector->getPrefix();
    }

    #[Override]
    public function listen(string $pattern, ListenerInterface|string $listener): Route
    {
        return $this->collector->addRoute($pattern, $listener, null);
    }

    #[Override]
    public function group(string $prefix, callable $routes): RouteGroup
    {
        $group = new RouteGroup($this->collector, $prefix);
        $routes($group);

        return $group;
    }

    /**
     * Все маршруты в порядке объявления.
     *
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        return $this->collector->getRoutes();
    }
}
