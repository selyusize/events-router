<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Topic\TopicPattern;

/**
 * Собранный маршрут из таблицы: полный шаблон, слушатель и все его middleware.
 *
 * Неизменяемый: собирается из объявлений, когда роутер строит таблицу маршрутов.
 * Таблица хранит маршруты в порядке объявления — в этом порядке и вызываются слушатели.
 */
final class CompiledRoute
{
    /**
     * @internal маршруты создаёт RouteTableBuilder
     *
     * @param class-string<ListenerInterface> $listener
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware в порядке выполнения
     */
    public function __construct(
        private readonly TopicPattern $pattern,
        private readonly string $listener,
        private readonly array $middleware,
    ) {}

    /**
     * Полный шаблон: префикс роутера, префиксы групп и шаблон из `listen()`.
     */
    public function getPattern(): TopicPattern
    {
        return $this->pattern;
    }

    /**
     * @return class-string<ListenerInterface>
     */
    public function getListener(): string
    {
        return $this->listener;
    }

    /**
     * Все middleware маршрута в порядке выполнения: от внешней группы к маршруту,
     * внутри каждого уровня — от добавленного последним к добавленному первым.
     *
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
