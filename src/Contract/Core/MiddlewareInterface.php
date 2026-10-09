<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Core;

use Closure;

/**
 * Middleware — обёртка вокруг слушателя, как middleware в Slim и Laravel.
 *
 * Получает событие и `$next` — продолжение цепочки: следующий middleware или сам слушатель. Может:
 *
 * - сделать что-то до и после вызова (логирование, транзакция, замер времени);
 * - передать дальше изменённое событие: `$next($event->withAttribute(...))`;
 * - не вызывать `$next` и тем самым не дать событию дойти до слушателя.
 *
 * Middleware навешиваются на маршруты и группы через `->add()`.
 * Как в Slim, добавленный последним выполняется первым.
 *
 * ```php
 * final class EventLogger implements MiddlewareInterface
 * {
 *     public function process(EventInterface $event, Closure $next): void
 *     {
 *         $this->logger->info('Событие ' . $event->getName());
 *
 *         $next($event);
 *     }
 * }
 * ```
 */
interface MiddlewareInterface
{
    /**
     * Обработать событие и, если нужно, передать его дальше по цепочке.
     *
     * @param Closure(EventInterface): void $next
     */
    public function process(EventInterface $event, Closure $next): void;
}
