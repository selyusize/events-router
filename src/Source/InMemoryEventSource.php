<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Source;

use Override;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Source\EventSourceInterface;

/**
 * Очередь событий в памяти — для тестов и простых сценариев.
 *
 * События, добавленные через `push()` во время обработки, тоже будут разосланы:
 * слушатель может положить в очередь следующее событие, и `run()` его обработает.
 *
 * ```php
 * $source = new InMemoryEventSource(new Event('order.created'), new Event('order.42.paid'));
 *
 * $events->run($source);   // 2
 * ```
 */
final class InMemoryEventSource implements EventSourceInterface
{
    /**
     * @var list<EventInterface>
     */
    private array $queue;

    public function __construct(EventInterface ...$events)
    {
        $this->queue = array_values($events);
    }

    /**
     * Добавить событие в конец очереди.
     */
    public function push(EventInterface $event): self
    {
        $this->queue[] = $event;

        return $this;
    }

    /**
     * Сколько событий ещё ждёт обработки.
     */
    public function count(): int
    {
        return \count($this->queue);
    }

    /**
     * Отдаёт события по одному и убирает их из очереди, пока очередь не опустеет.
     */
    #[Override]
    public function events(): iterable
    {
        while ($this->queue !== []) {
            yield array_shift($this->queue);
        }
    }
}
