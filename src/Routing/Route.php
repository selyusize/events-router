<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Topic\TopicPattern;

/**
 * Готовый маршрут из таблицы: полный шаблон, слушатель и все его middleware.
 *
 * Неизменяемый: собирается из объявлений, когда роутер строит таблицу маршрутов.
 * Приоритетов нет: слушатели одного события вызываются по возрастанию `getIndex()`,
 * то есть строго в порядке объявления маршрутов в файле.
 */
final class Route
{
    /**
     * @internal маршруты создаёт RouteCompiler
     *
     * @param class-string<ListenerInterface> $listener
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware в порядке выполнения
     * @param non-empty-string|null $name
     * @param int $index порядковый номер объявления, начиная с 0
     */
    public function __construct(
        private readonly TopicPattern $pattern,
        private readonly string $listener,
        private readonly array $middleware,
        private readonly ?string $name,
        private readonly int $index,
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

    /**
     * @return non-empty-string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Порядковый номер объявления: слушатели одного события вызываются по возрастанию.
     */
    public function getIndex(): int
    {
        return $this->index;
    }
}
