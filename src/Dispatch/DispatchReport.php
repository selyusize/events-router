<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Dispatch;

use Selyusize\EventsRouter\Contract\Core\EventInterface;

/**
 * Отчёт о рассылке события: кто из слушателей отработал, кто упал, кто не был вызван.
 *
 * Слушатели перечислены в порядке вызова — это порядок объявления маршрутов.
 *
 * ```php
 * $report = $events->dispatch(new Event('shop.order.42.paid'));
 *
 * if ($report->hasFailures()) {
 *     foreach ($report->getFailures() as $failed) {
 *         $logger->error($failed->getError()->getMessage());
 *     }
 * }
 * ```
 */
final class DispatchReport
{
    /**
     * @param list<ListenerReport> $listeners
     */
    public function __construct(
        private readonly EventInterface $event,
        private readonly array $listeners,
    ) {}

    /**
     * Событие, переданное в dispatch().
     */
    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    /**
     * Отчёты по всем подходящим маршрутам в порядке вызова.
     *
     * @return list<ListenerReport>
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    /**
     * Были ли у события слушатели.
     */
    public function hasListeners(): bool
    {
        return $this->listeners !== [];
    }

    public function hasFailures(): bool
    {
        return $this->getFailures() !== [];
    }

    /**
     * Отчёты по слушателям, которые упали.
     *
     * @return list<ListenerReport>
     */
    public function getFailures(): array
    {
        return array_values(array_filter($this->listeners, static fn (ListenerReport $report): bool => $report->isFailed()));
    }
}
