<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Dispatch\ListenerReport;
use Selyusize\EventsRouter\Dispatch\ListenerStatus;
use Selyusize\EventsRouter\Routing\Route;
use Throwable;

/**
 * Вызов одного слушателя: его цепочка middleware, замер времени, перехват исключения.
 *
 * Что делать с ошибкой дальше, решает Dispatcher.
 *
 * @internal
 */
final class ListenerInvoker
{
    public function __construct(
        private readonly HandlerResolver $resolver,
    ) {}

    public function invoke(Route $route, EventInterface $event): ListenerReport
    {
        $listener = new ListenerCall($route->getListener(), $this->resolver);
        $start = hrtime(true);

        try {
            Pipeline::wrap($route->getMiddleware(), $listener, $this->resolver)->handle($event);
            $status = $listener->isReached() ? ListenerStatus::Handled : ListenerStatus::Skipped;
            $error = null;
        } catch (Throwable $exception) {
            $status = ListenerStatus::Failed;
            $error = $exception;
        }

        return new ListenerReport($route, $event, $status, $error, (float)(hrtime(true) - $start) / 1e9);
    }

    public function skip(Route $route, EventInterface $event): ListenerReport
    {
        return new ListenerReport($route, $event, ListenerStatus::Skipped, null, 0.0);
    }
}
