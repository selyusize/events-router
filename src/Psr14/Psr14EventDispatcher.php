<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Psr14;

use Closure;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\Exception\InvalidEventName;
use Selyusize\EventsRouter\Exception\UnmappableEvent;

/**
 * Роутер под стандартным интерфейсом PSR-14 — для кода и библиотек,
 * которые знают только `Psr\EventDispatcher\EventDispatcherInterface`.
 *
 * Как из объекта получается событие:
 *
 * - объект реализует EventInterface — рассылается как есть;
 * - иначе имя события даёт маппер из конструктора, а сам объект становится payload.
 *
 * Объект без EventInterface и без маппера — исключение UnmappableEvent:
 * имя события не угадывается по имени класса.
 *
 * ```php
 * $dispatcher = new Psr14EventDispatcher(
 *     $events,
 *     static fn (object $event): string => match (true) {
 *         $event instanceof OrderPaid => 'shop.order.' . $event->orderId . '.paid',
 *     },
 * );
 *
 * $dispatcher->dispatch(new OrderPaid(42, 1500));   // слушатели получат OrderPaid в getPayload()
 * ```
 *
 * Как требует PSR-14, `dispatch()` возвращает тот же объект, что получил.
 * Остановка рассылки через `StoppableEventInterface` работает: роутер проверяет и событие, и payload.
 */
final class Psr14EventDispatcher implements EventDispatcherInterface
{
    /**
     * @param (Closure(object): string)|null $topic имя события для объекта без EventInterface
     */
    public function __construct(
        private readonly EventRouter $router,
        private readonly ?Closure $topic = null,
    ) {}

    /**
     * @template T of object
     *
     * @param T $event
     *
     * @return T
     *
     * @throws UnmappableEvent если имя события неизвестно
     * @throws InvalidEventName если маппер вернул некорректное имя
     */
    #[Override]
    public function dispatch(object $event): object
    {
        $this->router->dispatch($this->toEvent($event));

        return $event;
    }

    private function toEvent(object $event): EventInterface
    {
        if ($event instanceof EventInterface) {
            return $event;
        }

        if ($this->topic === null) {
            throw UnmappableEvent::forObject($event);
        }

        return new Event(($this->topic)($event), $event);
    }
}
