<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;
use Selyusize\EventsRouter\Dispatch\DispatchReport;
use Selyusize\EventsRouter\Dispatch\ErrorStrategyEnum;
use Selyusize\EventsRouter\Dispatch\ListenerReport;
use Selyusize\EventsRouter\Dispatch\ListenerStatusEnum;
use Selyusize\EventsRouter\Routing\RouteMatch;
use Selyusize\EventsRouter\Service\Error\PhpErrorLogHandler;
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
     * @param ContainerInterface|null $container откуда брать middleware, указанные именем класса
     */
    public function __construct(
        private readonly ?ContainerInterface $container = null,
        private readonly ErrorHandlerInterface $errorHandler = new PhpErrorLogHandler(),
        private readonly ErrorStrategyEnum $errorStrategy = ErrorStrategyEnum::Continue,
    ) {}

    public function withErrorHandler(ErrorHandlerInterface $handler): self
    {
        return new self($this->container, $handler, $this->errorStrategy);
    }

    public function withErrorStrategy(ErrorStrategyEnum $strategy): self
    {
        return new self($this->container, $this->errorHandler, $strategy);
    }

    /**
     * @param list<RouteMatch> $matches
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $routerMiddleware
     */
    public function dispatch(EventInterface $event, array $matches, array $routerMiddleware): DispatchReport
    {
        /** @var list<ListenerReport>|null $reports */
        $reports = null;

        $this->chain($routerMiddleware, function (EventInterface $event) use ($matches, &$reports): void {
            $reports = [];
            $stopped = false;

            foreach ($matches as $match) {
                $route = $match->getRoute();
                $listenerEvent = self::withParameters($event, $match);
                $stopped = $stopped || self::isStopped($listenerEvent) || self::isStopped($listenerEvent->getPayload());

                if ($stopped) {
                    $reports[] = new ListenerReport($route, $listenerEvent, ListenerStatusEnum::Skipped, null, 0.0);

                    continue;
                }

                $reached = false;
                $error = null;
                $start = hrtime(true);

                try {
                    $this->chain($route->getMiddleware(), static function (EventInterface $event) use ($route, &$reached): void {
                        $reached = true;
                        $route->getListener()::handle($event);
                    })($listenerEvent);
                } catch (Throwable $exception) {
                    $error = $exception;
                }

                $status = match (true) {
                    $error !== null => ListenerStatusEnum::Failed,
                    $reached => ListenerStatusEnum::Handled,
                    default => ListenerStatusEnum::Skipped,
                };
                $reports[] = new ListenerReport($route, $listenerEvent, $status, $error, (float)(hrtime(true) - $start) / 1e9);

                if ($error === null) {
                    continue;
                }

                if ($this->errorStrategy === ErrorStrategyEnum::Throw) {
                    throw $error;
                }

                $this->errorHandler->handle($error, $listenerEvent, $route->getListener());
                $stopped = $this->errorStrategy === ErrorStrategyEnum::Stop;
            }
        })($event);

        // middleware роутера не вызвал $next — ни один слушатель не получил событие
        $reports ??= array_map(
            static fn (RouteMatch $match): ListenerReport => new ListenerReport($match->getRoute(), self::withParameters($event, $match), ListenerStatusEnum::Skipped, null, 0.0),
            $matches,
        );

        return new DispatchReport($event, $reports);
    }

    /**
     * Цепочка: middleware по порядку, в конце — `$last`. Первый в списке выполняется первым.
     *
     * Middleware, указанный именем класса, создаётся в момент вызова: из контейнера,
     * а если контейнер не передан или не знает класс — через `new`.
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
                    $item = $this->container?->has($item) === true ? $this->container->get($item) : new $item();
                }

                $item->process($event, $next);
            };
        }

        return $next;
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
     * Payload — общий объект для всех копий события, поэтому флаг видят следующие слушатели.
     */
    private static function isStopped(mixed $value): bool
    {
        return $value instanceof StoppableEventInterface && $value->isPropagationStopped();
    }
}
