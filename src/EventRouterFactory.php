<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

use Psr\Container\ContainerInterface;
use Selyusize\EventsRouter\Routing\Revision;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Service\Dispatcher;
use Selyusize\EventsRouter\Service\RouteTableBuilder;

/**
 * Создаёт роутер — аналог `AppFactory` в Slim. Единственное место,
 * где части роутера создаются и связываются друг с другом.
 *
 * ```php
 * $events = EventRouterFactory::create($container);   // контейнер необязателен
 * (require __DIR__ . '/events.php')($events);
 * ```
 */
final class EventRouterFactory
{
    /**
     * @param ContainerInterface|null $container откуда брать middleware, указанные именем класса;
     *                                           без контейнера или если он не знает класс — `new` без аргументов.
     *                                           Слушатели статичные и из контейнера не берутся.
     */
    public static function create(?ContainerInterface $container = null): EventRouter
    {
        return new EventRouter(
            new RouteGroup(new Revision()),
            new RouteTableBuilder(),
            new Dispatcher($container),
        );
    }
}
