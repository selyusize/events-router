<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Routing;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

/**
 * То, на чём объявляют маршруты: сам роутер и любая группа.
 *
 * Аналог `RouteCollectorProxy` в Slim: `listen()` вместо `get()`/`post()`
 * и `group()` для общих префикса и middleware. Удобен как тип для своих
 * помощников, которые объявляют маршруты и на роутере, и в группе.
 */
interface RouteCollectorInterface
{
    /**
     * Повесить одного слушателя на шаблон топика.
     *
     * Несколько слушателей на одно событие — несколько вызовов `listen()`.
     * Вызываются они строго в порядке объявления, приоритетов нет.
     *
     * Если шаблон вместе с префиксами групп записан с ошибкой — InvalidTopicPattern.
     * Если класс слушателя не найден или не реализует ListenerInterface — InvalidRoute.
     *
     * @param string $pattern шаблон относительно префикса группы; в группе может быть пустым
     * @param class-string<ListenerInterface> $listener
     */
    public function listen(string $pattern, string $listener): RouteInterface;

    /**
     * Группа маршрутов с общим префиксом и общими middleware.
     *
     * Пустой префикс — группа только ради общих middleware.
     *
     * @param callable(RouteGroupInterface): void $routes
     */
    public function group(string $prefix, callable $routes): RouteGroupInterface;
}
