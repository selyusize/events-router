<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Dispatch;

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Routing\CompiledRoute;
use Throwable;

/**
 * Отчёт об одном слушателе: какой маршрут сработал, с каким событием,
 * чем закончилось и сколько заняло времени вместе с middleware.
 */
final class ListenerReport
{
    public function __construct(
        private readonly CompiledRoute $route,
        private readonly EventInterface $event,
        private readonly ListenerStatusEnum $status,
        private readonly ?Throwable $error,
        private readonly float $duration,
    ) {}

    public function getRoute(): CompiledRoute
    {
        return $this->route;
    }

    /**
     * @return class-string<ListenerInterface>
     */
    public function getListener(): string
    {
        return $this->route->getListener();
    }

    /**
     * Событие, переданное в цепочку слушателя: с параметрами его маршрута в атрибутах.
     */
    public function getEvent(): EventInterface
    {
        return $this->event;
    }

    public function getStatus(): ListenerStatusEnum
    {
        return $this->status;
    }

    public function isFailed(): bool
    {
        return $this->status === ListenerStatusEnum::Failed;
    }

    /**
     * Исключение, если статус Failed.
     */
    public function getError(): ?Throwable
    {
        return $this->error;
    }

    /**
     * Время работы слушателя вместе с его middleware, в секундах.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }
}
