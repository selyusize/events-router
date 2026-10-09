<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Routing\CompiledRoute;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Routing\RouteTable;
use Selyusize\EventsRouter\Topic\TopicPattern;

/**
 * Строит из дерева групп таблицу собранных маршрутов.
 *
 * Обходит группы в порядке объявления, склеивает префиксы и шаблон,
 * разворачивает middleware групп и маршрута в один список в порядке выполнения.
 *
 * @internal
 */
final class RouteTableBuilder
{
    /**
     * @throws InvalidTopicPattern если с префиксом роутера какой-то шаблон стал некорректным
     */
    public function build(RouteGroup $root, string $prefix): RouteTable
    {
        $routes = [];
        $this->collect($root, $prefix, [], $routes);

        return new RouteTable($routes);
    }

    /**
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $outerMiddleware middleware внешних групп
     * @param list<CompiledRoute> $routes
     */
    private function collect(RouteGroup $group, string $path, array $outerMiddleware, array &$routes): void
    {
        $middleware = [...$outerMiddleware, ...$group->getMiddleware()];

        foreach ($group->getChildren() as $child) {
            if ($child instanceof RouteGroup) {
                $this->collect($child, RouteGroup::join($path, $child->getPrefix()), $middleware, $routes);

                continue;
            }

            $routes[] = new CompiledRoute(
                TopicPattern::fromString(RouteGroup::join($path, $child->getPattern())),
                $child->getListener(),
                [...$middleware, ...$child->getMiddleware()],
            );
        }
    }
}
