<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Topic\TopicPattern;

/**
 * Маршрут: шаблон топика → один слушатель + его middleware.
 *
 * Создаётся вызовом `listen()` на роутере или группе. Настраивается цепочкой:
 *
 * ```php
 * $group->listen('{order_id}.paid', MarkOrderPaid::class)
 *     ->add(IdempotencyGuard::class)
 *     ->name('order.mark_paid');
 * ```
 *
 * Приоритетов нет: слушатели одного события вызываются строго в порядке объявления
 * маршрутов в файле. Чтобы слушатель сработал раньше, объявите его выше.
 */
final class Route
{
    /**
     * @var list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * @var non-empty-string|null
     */
    private ?string $name = null;

    /**
     * @internal маршруты создаёт RouteCollector
     *
     * @param class-string<ListenerInterface>|ListenerInterface $listener
     * @param int $index порядковый номер объявления, начиная с 0
     */
    public function __construct(
        private TopicPattern $pattern,
        private readonly string $path,
        private readonly ListenerInterface|string $listener,
        private readonly ?RouteGroup $group,
        private readonly int $index,
    ) {}

    /**
     * Добавить middleware только этому маршруту.
     *
     * Добавленный последним выполняется первым, как в Slim.
     * Middleware групп выполняются раньше middleware маршрута.
     *
     * @param class-string<MiddlewareInterface>|MiddlewareInterface $middleware
     */
    public function add(MiddlewareInterface|string $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * Имя маршрута — для отладки, логов и, позже, асинхронной очереди.
     *
     * @param non-empty-string $name
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Полный шаблон: префикс роутера, префиксы групп и шаблон из `listen()`.
     */
    public function getPattern(): TopicPattern
    {
        return $this->pattern;
    }

    /**
     * @return class-string<ListenerInterface>|ListenerInterface
     */
    public function getListener(): ListenerInterface|string
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
        $groups = $this->group?->getGroups() ?? [];

        return array_merge(
            ...array_map(static fn (RouteGroup $group): array => $group->getMiddleware(), $groups),
            ...[array_reverse($this->middleware)],
        );
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

    /**
     * Шаблон без префикса роутера.
     *
     * @internal
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @internal вызывается роутером при смене префикса
     */
    public function setPattern(TopicPattern $pattern): void
    {
        $this->pattern = $pattern;
    }
}
