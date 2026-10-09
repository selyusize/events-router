<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

/**
 * Таблица собранных маршрутов в порядке объявления. Неизменяемая.
 */
final class RouteTable
{
    /**
     * @param list<CompiledRoute> $routes
     */
    public function __construct(
        private readonly array $routes,
    ) {}

    /**
     * @return list<CompiledRoute>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Все маршруты, чей шаблон подходит под топик, строго в порядке объявления.
     *
     * В отличие от HTTP-роутера, возвращает **все** совпадения: у события может быть
     * сколько угодно слушателей.
     *
     * @return list<RouteMatch> пустой список, если слушателей нет
     */
    public function match(string $topic): array
    {
        $matches = [];

        foreach ($this->routes as $route) {
            $parameters = $route->getPattern()->match($topic);

            if ($parameters !== null) {
                $matches[] = new RouteMatch($route, $parameters);
            }
        }

        return $matches;
    }
}
