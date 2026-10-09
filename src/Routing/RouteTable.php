<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

/**
 * Готовая таблица маршрутов в порядке объявления. Неизменяемая.
 *
 * По ней ищутся слушатели события; позже её же можно будет сохранить в кэш.
 */
final class RouteTable
{
    /**
     * @param list<Route> $routes
     */
    public function __construct(
        private readonly array $routes,
    ) {}

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
