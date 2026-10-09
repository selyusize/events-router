<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Override;
use Selyusize\EventsRouter\Routing\RouteMatch;
use Selyusize\EventsRouter\Routing\RouteTable;

/**
 * Поиск маршрутов перебором: каждый шаблон сопоставляется с топиком по очереди.
 *
 * Таблица хранит маршруты в порядке объявления, поэтому результат уже упорядочен.
 * Время поиска растёт с числом маршрутов; для больших таблиц позже появится
 * поиск по дереву.
 *
 * @internal
 */
final class RouteMatcher implements RouteMatcherInterface
{
    #[Override]
    public function match(RouteTable $table, string $topic): array
    {
        $matches = [];

        foreach ($table->getRoutes() as $route) {
            $parameters = $route->getPattern()->match($topic);

            if ($parameters !== null) {
                $matches[] = new RouteMatch($route, $parameters);
            }
        }

        return $matches;
    }
}
