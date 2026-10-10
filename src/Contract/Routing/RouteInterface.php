<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Routing;

use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

/**
 * Маршрут: то, что возвращает `listen()`.
 */
interface RouteInterface
{
    /**
     * Добавить middleware только этому маршруту.
     *
     * Добавленный последним выполняется первым, как в Slim.
     * Middleware групп выполняются раньше middleware маршрута.
     * Если класс middleware не найден или не реализует MiddlewareInterface — InvalidRoute.
     *
     * @param class-string<MiddlewareInterface>|MiddlewareInterface $middleware
     */
    public function add(MiddlewareInterface|string $middleware): self;
}
