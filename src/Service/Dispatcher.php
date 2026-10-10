<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;
use Selyusize\EventsRouter\Dispatch\DispatchReport;
use Selyusize\EventsRouter\Dispatch\ErrorStrategyEnum;
use Selyusize\EventsRouter\Dispatch\ListenerReport;
use Selyusize\EventsRouter\Locale\Messages;
use Selyusize\EventsRouter\Routing\RouteMatch;
use Selyusize\EventsRouter\Routing\RouteTable;
use Selyusize\EventsRouter\Service\Error\FailureFormatter;
use Selyusize\EventsRouter\Service\Error\PsrLoggerErrorHandler;
use Throwable;

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
     * @param ContainerInterface $container откуда брать middleware, указанные именем класса
     * @param LoggerInterface $logger лог роутера: сюда пишутся ошибки, пока не задан свой обработчик
     * @param bool $logDispatch писать в лог каждую рассылку: событие, атрибуты, слушателей
     * @param ErrorHandlerInterface|null $errorHandler свой обработчик ошибок; null — запись в $logger
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
        private readonly bool $logDispatch = false,
        private readonly ?ErrorHandlerInterface $errorHandler = null,
        private readonly ErrorStrategyEnum $errorStrategy = ErrorStrategyEnum::Continue,
    ) {}

    public function withLogger(LoggerInterface $logger): self
    {
        return new self($this->container, $logger, $this->logDispatch, $this->errorHandler, $this->errorStrategy);
    }

    public function withErrorHandler(ErrorHandlerInterface $handler): self
    {
        return new self($this->container, $this->logger, $this->logDispatch, $handler, $this->errorStrategy);
    }

    public function withErrorStrategy(ErrorStrategyEnum $strategy): self
    {
        return new self($this->container, $this->logger, $this->logDispatch, $this->errorHandler, $strategy);
    }

    /**
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $routerMiddleware
     */
    public function dispatch(EventInterface $event, RouteTable $table, array $routerMiddleware): DispatchReport
    {
        $dispatchStart = $this->logDispatch ? hrtime(true) : 0;
        $plan = $table->plan($event->getName());

        if ($routerMiddleware === []) {
            $report = $this->deliver($event, $event, $plan);
        } else {
            $report = null;
            $this->chain($routerMiddleware, function (EventInterface $delivered) use ($event, $plan, &$report): void {
                $report = $this->deliver($event, $delivered, $plan);
            })($event);

            // middleware роутера не вызвал $next — ни один слушатель не получил событие
            $report ??= new DispatchReport($event, null, $plan['matches'], [], array_fill(0, \count($plan['matches']), true));
        }

        if ($this->logDispatch) {
            $this->logger->info(\sprintf(
                Messages::translate('events-router: %s, слушателей: %d, %.1f мс'),
                $event->getName(),
                \count($plan['matches']),
                (float)(hrtime(true) - $dispatchStart) / 1e6,
            ), array_filter([
                'attributes' => $event->getAttributes(),
                'listeners' => array_map(static fn (ListenerReport $listener): array => array_filter([
                    'listener' => FailureFormatter::readableClass($listener->getListener()),
                    'status' => $listener->getStatus()->name,
                    'ms' => round($listener->getDuration() * 1000.0, 1),
                    // что получил слушатель: параметры маршрута и атрибуты от middleware
                    'attributes' => $listener->getEvent()->getAttributes(),
                    'error' => $listener->getError() === null ? null : get_debug_type($listener->getError()) . ': ' . $listener->getError()->getMessage(),
                ], static fn (mixed $value): bool => $value !== null && $value !== []), $report->getListeners()),
            ], static fn (array $value): bool => $value !== []));
        }

        return $report;
    }

    /**
     * Слушатели по очереди, каждый со своими middleware. Самый частый путь рассылки, поэтому
     * здесь нет лишних объектов: статусы и время копятся в массивах, а ListenerReport
     * создаст DispatchReport, когда их запросят.
     *
     * @param EventInterface $original событие, переданное в dispatch()
     * @param EventInterface $event событие после middleware роутера
     * @param array{matches: list<RouteMatch>, listeners: list<class-string<ListenerInterface>>, middleware: list<list<class-string<MiddlewareInterface>|MiddlewareInterface>>, parameters: list<array<non-empty-string, non-empty-string>>, direct: bool} $plan
     */
    private function deliver(EventInterface $original, EventInterface $event, array $plan): DispatchReport
    {
        // PSR-14: событие или его payload может сказать «дальше не рассылать». Payload — общий объект
        // для всех копий события, поэтому флаг, выставленный одним слушателем, видят следующие
        /** @psalm-suppress MixedAssignment payload может быть чем угодно, нужна только проверка на StoppableEventInterface */
        $payload = $event->getPayload();
        $stoppable = $event instanceof StoppableEventInterface || $payload instanceof StoppableEventInterface;

        // Самый частый случай: слушатели без middleware и параметров, событие нельзя остановить.
        // Тогда на слушателя — только вызов и замер времени. Записываются только отклонения:
        // слушатель без записи в $skipped и $errors отработал
        if ($plan['direct'] && !$stoppable) {
            $elapsed = [];
            $skipped = [];
            $errors = [];
            $start = hrtime(true);

            foreach ($plan['listeners'] as $index => $listener) {
                try {
                    $listener::handle($event);
                } catch (Throwable $error) {
                    $elapsed[$index] = hrtime(true) - $start;
                    $errors[$index] = $error;

                    if ($this->errorStrategy === ErrorStrategyEnum::Throw) {
                        throw $error;
                    }

                    ($this->errorHandler ?? new PsrLoggerErrorHandler($this->logger))->handle($error, $event, $listener);

                    if ($this->errorStrategy === ErrorStrategyEnum::Stop) {
                        $skipped = array_fill($index + 1, \count($plan['listeners']) - $index - 1, true);

                        break;
                    }

                    $start = hrtime(true);

                    continue;
                }

                $elapsed[$index] = ($end = hrtime(true)) - $start;
                $start = $end;
            }

            return new DispatchReport($original, $event, $plan['matches'], $elapsed, $skipped, $errors);
        }

        ['listeners' => $listeners, 'middleware' => $middleware, 'parameters' => $parameters] = $plan;

        $elapsed = [];
        $skipped = [];
        $errors = [];
        $events = [];
        $stopped = false;
        $start = hrtime(true);

        foreach ($listeners as $index => $listener) {
            $listenerEvent = $event;

            if ($parameters[$index] !== []) {
                foreach ($parameters[$index] as $name => $value) {
                    $listenerEvent = $listenerEvent->withAttribute($name, $value);
                }

                $events[$index] = $listenerEvent;
            }

            if ($stoppable && !$stopped) {
                $stopped = ($listenerEvent instanceof StoppableEventInterface && $listenerEvent->isPropagationStopped())
                    || ($payload instanceof StoppableEventInterface && $payload->isPropagationStopped());
            }

            if ($stopped) {
                $skipped[$index] = true;

                continue;
            }

            try {
                // Без middleware — прямой вызов: цепочка из замыканий не нужна
                if ($middleware[$index] === []) {
                    $listener::handle($listenerEvent);
                } else {
                    $reached = false;
                    $this->chain($middleware[$index], static function (EventInterface $event) use ($listener, &$reached): void {
                        $reached = true;
                        $listener::handle($event);
                    })($listenerEvent);

                    if (!$reached) {
                        $skipped[$index] = true;
                    }
                }
            } catch (Throwable $error) {
                $elapsed[$index] = hrtime(true) - $start;
                $errors[$index] = $error;

                if ($this->errorStrategy === ErrorStrategyEnum::Throw) {
                    throw $error;
                }

                ($this->errorHandler ?? new PsrLoggerErrorHandler($this->logger))->handle($error, $listenerEvent, $listener);
                $stopped = $this->errorStrategy === ErrorStrategyEnum::Stop;
                $start = hrtime(true);

                continue;
            }

            // Конец одного слушателя — начало следующего: один вызов hrtime() на слушателя
            $end = hrtime(true);
            $elapsed[$index] = $end - $start;
            $start = $end;
        }

        return new DispatchReport($original, $event, $plan['matches'], $elapsed, $skipped, $errors, $events);
    }

    /**
     * Цепочка: middleware по порядку, в конце — `$last`. Первый в списке выполняется первым.
     *
     * Middleware, указанный именем класса, берётся из контейнера в момент вызова.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware
     * @param Closure(EventInterface): void $last
     *
     * @return Closure(EventInterface): void
     */
    private function chain(array $middleware, Closure $last): Closure
    {
        $next = $last;

        foreach (array_reverse($middleware) as $item) {
            $next = function (EventInterface $event) use ($item, $next): void {
                if (\is_string($item)) {
                    /** @var MiddlewareInterface $item класс указан как class-string<MiddlewareInterface> */
                    $item = $this->container->get($item);
                }

                $item->process($event, $next);
            };
        }

        return $next;
    }
}
