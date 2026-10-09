<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Error;

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Throwable;

/**
 * Что делать с ошибкой слушателя: записать в лог, отправить в мониторинг и т. п.
 *
 * Вызывается для каждого упавшего слушателя сразу после его вызова.
 *
 * ```php
 * final class ErrorHandler implements ErrorHandlerInterface
 * {
 *     public function handle(Throwable $error, EventInterface $event, string $listener): void
 *     {
 *         Logger::toFile('events/error', [
 *             'event' => $event->getName(),
 *             'listener' => $listener,
 *             'message' => $error->getMessage(),
 *         ]);
 *     }
 * }
 *
 * $events->setErrorHandler(ErrorHandler::class);
 * ```
 */
interface ErrorHandlerInterface
{
    /**
     * Обработать ошибку слушателя.
     *
     * @param Throwable $error исключение из слушателя или его middleware
     * @param EventInterface $event событие, которое получил слушатель, с параметрами маршрута в атрибутах
     * @param class-string<ListenerInterface> $listener класс упавшего слушателя
     */
    public function handle(Throwable $error, EventInterface $event, string $listener): void;
}
