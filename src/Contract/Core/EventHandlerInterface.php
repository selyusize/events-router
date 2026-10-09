<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Core;

/**
 * Следующее звено цепочки, которое получает middleware в `$next`.
 *
 * Аналог `RequestHandlerInterface` в PSR-15: за ним может стоять другой middleware
 * или сам слушатель. Реализует роутер, в своём коде достаточно вызвать
 * `$next->handle($event)`.
 */
interface EventHandlerInterface
{
    /**
     * Передать событие дальше по цепочке.
     */
    public function handle(EventInterface $event): void;
}
