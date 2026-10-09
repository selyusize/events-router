<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Source;

use Selyusize\EventsRouter\Contract\Core\EventInterface;

/**
 * Источник событий для воркера: очередь, вебхук, файл, память.
 *
 * Роутер сам забирает события в `EventRouter::run()` и рассылает их по одному.
 * Удобнее всего реализовать генератором: код после `yield` выполняется, когда
 * событие уже разослано, — там можно подтвердить сообщение в очереди.
 *
 * ```php
 * final class QueueEventSource implements EventSourceInterface
 * {
 *     public function events(): iterable
 *     {
 *         while ($message = $this->queue->receive()) {
 *             yield new Event($message->getRoutingKey(), $message->getBody());
 *
 *             $this->queue->acknowledge($message);   // событие разослано
 *         }
 *     }
 * }
 * ```
 *
 * Источник заканчивается, когда заканчивается итерация. Бесконечная очередь
 * может не заканчиваться никогда — тогда воркер работает до остановки процесса.
 */
interface EventSourceInterface
{
    /**
     * События по одному, в порядке получения.
     *
     * @return iterable<EventInterface>
     */
    public function events(): iterable;
}
