<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

use Psr\Container\ContainerInterface;
use Selyusize\EventsRouter\Routing\Revision;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Service\Dispatcher;
use Selyusize\EventsRouter\Service\HandlerResolver;
use Selyusize\EventsRouter\Service\ListenerInvoker;
use Selyusize\EventsRouter\Service\RouteCompiler;
use Selyusize\EventsRouter\Service\RouteMatcher;

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
     * @param ContainerInterface|null $container откуда брать middleware и обработчик ошибок;
     *                                           без контейнера или если он не знает класс — `new` без аргументов.
     *                                           Слушатели статичные и из контейнера не берутся.
     */
    public static function create(?ContainerInterface $container = null): EventRouter
    {
        $resolver = new HandlerResolver($container);

        return new EventRouter(
            new RouteGroup(new Revision()),
            new RouteCompiler(),
            new RouteMatcher(),
            new Dispatcher(new ListenerInvoker($resolver), $resolver),
        );
    }
}
