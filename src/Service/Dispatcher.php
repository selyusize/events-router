<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Psr\EventDispatcher\StoppableEventInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;
use Selyusize\EventsRouter\Dispatch\DispatchReport;
use Selyusize\EventsRouter\Dispatch\ErrorStrategy;
use Selyusize\EventsRouter\Dispatch\ListenerReport;
use Selyusize\EventsRouter\Routing\RouteMatch;
use Selyusize\EventsRouter\Service\Error\PhpErrorLogHandler;

/**
 * Рассылка события найденным маршрутам.
 *
 * ```text
 * middleware роутера (один раз на событие)
 *   └─ для каждого маршрута в порядке объявления:
 *        middleware групп и маршрута → слушатель
 * ```
 *
 * Ошибка слушателя попадает в отчёт и в обработчик ошибок, дальше всё решает
 * стратегия: продолжить, остановиться или выбросить исключение наружу.
 * Исключение из middleware роутера не относится ни к одному слушателю
 * и выходит из dispatch() наружу при любой стратегии.
 *
 * Настройки ошибок неизменяемы: with*() возвращают новый диспетчер.
 *
 * @internal
 */
final class Dispatcher
{
    /**
     * @param class-string<ErrorHandlerInterface>|ErrorHandlerInterface $errorHandler
     */
    public function __construct(
        private readonly ListenerInvoker $invoker,
        private readonly HandlerResolver $resolver,
        private readonly ErrorHandlerInterface|string $errorHandler = PhpErrorLogHandler::class,
        private readonly ErrorStrategy $errorStrategy = ErrorStrategy::Continue,
    ) {}

    /**
     * @param class-string<ErrorHandlerInterface>|ErrorHandlerInterface $handler
     */
    public function withErrorHandler(ErrorHandlerInterface|string $handler): self
    {
        return new self($this->invoker, $this->resolver, $handler, $this->errorStrategy);
    }

    public function withErrorStrategy(ErrorStrategy $strategy): self
    {
        return new self($this->invoker, $this->resolver, $this->errorHandler, $strategy);
    }

    /**
     * @param list<RouteMatch> $matches
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $routerMiddleware
     */
    public function dispatch(EventInterface $event, array $matches, array $routerMiddleware): DispatchReport
    {
        $reports = null;

        $listeners = new CallbackHandler(function (EventInterface $event) use ($matches, &$reports): void {
            $reports = $this->callListeners($event, $matches);
        });

        Pipeline::wrap($routerMiddleware, $listeners, $this->resolver)->handle($event);

        // middleware роутера не вызвал $next — ни один слушатель не получил событие
        $reports ??= array_map(
            fn (RouteMatch $match): ListenerReport => $this->invoker->skip($match->getRoute(), self::withParameters($event, $match)),
            $matches,
        );

        return new DispatchReport($event, $reports);
    }

    /**
     * @param list<RouteMatch> $matches
     *
     * @return list<ListenerReport>
     */
    private function callListeners(EventInterface $event, array $matches): array
    {
        $reports = [];
        $stopped = false;

        foreach ($matches as $match) {
            $listenerEvent = self::withParameters($event, $match);

            if ($stopped || self::isPropagationStopped($listenerEvent)) {
                $reports[] = $this->invoker->skip($match->getRoute(), $listenerEvent);

                continue;
            }

            $report = $this->invoker->invoke($match->getRoute(), $listenerEvent);
            $reports[] = $report;
            $error = $report->getError();

            if ($error === null) {
                continue;
            }

            if ($this->errorStrategy === ErrorStrategy::Throw) {
                throw $error;
            }

            $this->resolver->errorHandler($this->errorHandler)->handle($error, $listenerEvent, $report->getListener());
            $stopped = $this->errorStrategy === ErrorStrategy::Stop;
        }

        return $reports;
    }

    /**
     * Копия события с параметрами маршрута в атрибутах. Параметр перекрывает
     * одноимённый атрибут, добавленный раньше.
     */
    private static function withParameters(EventInterface $event, RouteMatch $match): EventInterface
    {
        foreach ($match->getParameters() as $name => $value) {
            $event = $event->withAttribute($name, $value);
        }

        return $event;
    }

    /**
     * PSR-14: событие или его payload может сказать «дальше не рассылать».
     *
     * Payload — общий объект для всех копий события, поэтому флаг, выставленный
     * одним слушателем, видят следующие.
     */
    private static function isPropagationStopped(EventInterface $event): bool
    {
        return self::isStopped($event) || self::isStopped($event->getPayload());
    }

    private static function isStopped(mixed $value): bool
    {
        return $value instanceof StoppableEventInterface && $value->isPropagationStopped();
    }
}
