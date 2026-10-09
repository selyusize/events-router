<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Routing;

use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

/**
 * Группа маршрутов: то, что получает замыкание в `group()`.
 */
interface RouteGroupInterface extends RouteCollectorInterface
{
    /**
     * Добавить middleware всем маршрутам группы, включая вложенные группы.
     *
     * Добавленный последним выполняется первым, как в Slim.
     *
     * @param class-string<MiddlewareInterface>|MiddlewareInterface $middleware
     */
    public function add(MiddlewareInterface|string $middleware): self;
}
