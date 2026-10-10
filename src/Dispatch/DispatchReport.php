<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Dispatch;

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Routing\RouteMatch;
use Throwable;

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
     * @var list<ListenerReport>|null
     */
    private ?array $listeners = null;

    /**
     * Отчёты по слушателям собираются при первом обращении: рассылка записывает только время
     * и отклонения, а объекты создаёт тот, кому они действительно нужны.
     *
     * @internal отчёт создаёт Dispatcher
     *
     * @param EventInterface|null $delivered событие после middleware роутера; null — middleware не вызвал `$next`
     * @param list<RouteMatch> $matches подходящие маршруты в порядке вызова
     * @param array<int, int> $elapsed время вызванных слушателей, наносекунды
     * @param array<int, true> $skipped слушатели, которые не получили событие
     * @param array<int, Throwable> $errors исключения упавших слушателей
     * @param array<int, EventInterface> $events события, которые получили слушатели с параметрами маршрута
     */
    public function __construct(
        private readonly EventInterface $event,
        private readonly ?EventInterface $delivered,
        private readonly array $matches,
        private readonly array $elapsed,
        private readonly array $skipped = [],
        private readonly array $errors = [],
        private readonly array $events = [],
    ) {}

    /**
     * Событие, переданное в dispatch().
     */
    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    /**
     * Отчёты по каждому подходящему маршруту, в порядке вызова слушателей.
     *
     * @return list<ListenerReport>
     */
    public function getListeners(): array
    {
        if ($this->listeners !== null) {
            return $this->listeners;
        }

        $this->listeners = [];

        foreach ($this->matches as $index => $match) {
            // Событие слушателя: то, что вышло из middleware роутера, с параметрами маршрута
            $event = $this->events[$index] ?? $this->delivered ?? $this->event;

            if (!isset($this->events[$index])) {
                foreach ($match->getParameters() as $name => $value) {
                    $event = $event->withAttribute($name, $value);
                }
            }

            $status = match (true) {
                isset($this->errors[$index]) => ListenerStatusEnum::Failed,
                isset($this->skipped[$index]) => ListenerStatusEnum::Skipped,
                default => ListenerStatusEnum::Handled,
            };

            $this->listeners[] = new ListenerReport($match->getRoute(), $event, $status, $this->errors[$index] ?? null, (float)($this->elapsed[$index] ?? 0) / 1e9);
        }

        return $this->listeners;
    }

    /**
     * Были ли у события слушатели.
     */
    public function hasListeners(): bool
    {
        return $this->matches !== [];
    }

    /**
     * Упал ли хотя бы один слушатель.
     */
    public function hasFailures(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Отчёты по слушателям, которые упали.
     *
     * @return list<ListenerReport>
     */
    public function getFailures(): array
    {
        return array_values(array_filter($this->getListeners(), static fn (ListenerReport $report): bool => $report->isFailed()));
    }
}
