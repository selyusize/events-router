<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;

/**
 * То, на чём объявляют маршруты: сам роутер и любая группа.
 *
 * Аналог `RouteCollectorProxy` в Slim: `listen()` вместо `get()`/`post()`
 * и `group()` для общих префикса и middleware.
 */
interface RouteCollectorInterface
{
    /**
     * Повесить одного слушателя на шаблон топика.
     *
     * Несколько слушателей на одно событие — несколько вызовов `listen()`.
     * Вызываются они строго в порядке объявления, приоритетов нет.
     *
     * @param string $pattern шаблон относительно префикса группы; в группе может быть пустым
     * @param class-string<ListenerInterface> $listener
     *
     * @throws InvalidTopicPattern если шаблон вместе с префиксами групп записан с ошибкой
     */
    public function listen(string $pattern, string $listener): RouteDefinition;

    /**
     * Группа маршрутов с общим префиксом и общими middleware.
     *
     * Пустой префикс — группа только ради общих middleware.
     *
     * @param callable(RouteGroup): void $routes
     *
     * @throws InvalidTopicPattern если шаблон маршрута внутри группы записан с ошибкой
     */
    public function group(string $prefix, callable $routes): RouteGroup;
}
