<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Core;

/**
 * Middleware — обёртка вокруг слушателя, как PSR-15 middleware вокруг Action.
 *
 * Получает событие и следующее звено цепочки `$next` (другой middleware или слушатель). Может:
 *
 * - сделать что-то до и после вызова (логирование, транзакция, замер времени);
 * - передать дальше изменённое событие (`$event->withAttribute(...)`);
 * - не вызывать `$next` и тем самым не дать событию дойти до слушателя.
 *
 * Middleware навешиваются на маршруты и группы через `->add()`.
 * Как в Slim, добавленный последним выполняется первым.
 *
 * ```php
 * final class EventLogger implements MiddlewareInterface
 * {
 *     public function process(EventInterface $event, EventHandlerInterface $next): void
 *     {
 *         $this->logger->info('Событие ' . $event->getName());
 *
 *         $next->handle($event);
 *     }
 * }
 * ```
 */
interface MiddlewareInterface
{
    /**
     * Обработать событие и, если нужно, передать его следующему звену.
     */
    public function process(EventInterface $event, EventHandlerInterface $next): void;
}
