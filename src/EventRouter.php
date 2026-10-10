<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

use Closure;
use Override;
use Psr\Log\LoggerInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;
use Selyusize\EventsRouter\Contract\Routing\RouteCollectorInterface;
use Selyusize\EventsRouter\Contract\Source\EventSourceInterface;
use Selyusize\EventsRouter\Dispatch\DispatchReport;
use Selyusize\EventsRouter\Dispatch\ErrorStrategyEnum;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Routing\CompiledRoute;
use Selyusize\EventsRouter\Routing\Route;
use Selyusize\EventsRouter\Routing\RouteAssert;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Routing\RouteMatch;
use Selyusize\EventsRouter\Routing\RouteTable;
use Selyusize\EventsRouter\Service\Dispatcher;
use Selyusize\EventsRouter\Service\RouteTableBuilder;

/**
 * Роутер событий — аналог `Slim\App`. Создаётся через EventRouterFactory.
 *
 * Маршруты описываются в отдельном файле, так же как HTTP-маршруты в Slim:
 *
 * ```php
 * return static function (EventRouter $events): void {
 *     $events->setPrefix('shop');
 *
 *     $events->group('order', static function (RouteGroup $group): void {
 *         $group->listen('created', ReserveStock::class);
 *         $group->listen('created', SendConfirmationEmail::class);
 *         $group->listen('{order_id}.paid', MarkOrderPaid::class);
 *     })
 *         ->add(EventLogger::class);
 * };
 * ```
 *
 * Рассылка:
 *
 * ```php
 * $events = EventRouterFactory::create($container);
 * (require __DIR__ . '/events.php')($events);
 *
 * $report = $events->dispatch(new Event('shop.order.42.paid', ['amount' => 1500]));
 * ```
 *
 * Сам роутер ничего не создаёт: объявления маршрутов хранит корневая группа,
 * таблицу строит RouteTableBuilder, рассылает Dispatcher.
 */
final class EventRouter implements RouteCollectorInterface
{
    private string $prefix = '';

    /**
     * @var list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    private array $middleware = [];

    private ?RouteTable $table = null;

    private int $tableRevision = -1;

    /**
     * @internal используйте EventRouterFactory::create()
     */
    public function __construct(
        private readonly RouteGroup $routes,
        private readonly RouteTableBuilder $builder,
        private Dispatcher $dispatcher,
    ) {}

    /**
     * Общий префикс всех топиков — аналог `setBasePath()` в Slim.
     *
     * Можно вызвать в любой момент: таблица маршрутов пересобирается сразу,
     * и при ошибке роутер остаётся с прежним префиксом. Пустая строка убирает префикс.
     *
     * @throws InvalidTopicPattern если с новым префиксом какой-то шаблон станет некорректным
     */
    public function setPrefix(string $prefix): self
    {
        $this->table = $this->builder->build($this->routes, $prefix);
        $this->tableRevision = $this->routes->getRevision();
        $this->prefix = $prefix;

        return $this;
    }

    #[Override]
    public function listen(string $pattern, string $listener): Route
    {
        return $this->routes->listen($pattern, $listener);
    }

    #[Override]
    public function group(string $prefix, callable $routes): RouteGroup
    {
        return $this->routes->group($prefix, $routes);
    }

    /**
     * Middleware уровня роутера — аналог `$app->add()` в Slim.
     *
     * Выполняется **один раз на событие** и оборачивает всех его слушателей,
     * даже если слушателей нет. Подходит для трассировки, общего лога, транзакции.
     * Добавленный последним выполняется первым.
     *
     * @param class-string<MiddlewareInterface>|MiddlewareInterface $middleware
     */
    public function add(MiddlewareInterface|string $middleware): self
    {
        RouteAssert::middleware($middleware);

        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * Лог роутера вместо файлового (FileLogger по пути `log_path`): например, Monolog проекта.
     * Сюда пишутся ошибки слушателей, пока не задан свой обработчик через setErrorHandler().
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->dispatcher = $this->dispatcher->withLogger($logger);

        return $this;
    }

    /**
     * Куда отправлять ошибки слушателей. По умолчанию — в лог роутера (см. setLogger()).
     */
    public function setErrorHandler(ErrorHandlerInterface $handler): self
    {
        $this->dispatcher = $this->dispatcher->withErrorHandler($handler);

        return $this;
    }

    /**
     * Что делать после ошибки слушателя. По умолчанию — ErrorStrategyEnum::Continue.
     */
    public function setErrorStrategy(ErrorStrategyEnum $strategy): self
    {
        $this->dispatcher = $this->dispatcher->withErrorStrategy($strategy);

        return $this;
    }

    /**
     * Какие маршруты получат событие с таким топиком.
     *
     * Возвращает все подходящие маршруты строго в порядке объявления,
     * у каждого — свои параметры из шаблона.
     *
     * ```php
     * foreach ($events->match('shop.order.42.paid') as $match) {
     *     $match->getRoute()->getListener();   // MarkOrderPaid::class
     *     $match->getParameters();             // ['order_id' => '42']
     * }
     * ```
     *
     * @return list<RouteMatch>
     */
    public function match(string $topic): array
    {
        return $this->table()->match($topic);
    }

    /**
     * Все маршруты в порядке объявления, с полными шаблонами и middleware.
     *
     * @return list<CompiledRoute>
     */
    public function getRoutes(): array
    {
        return $this->table()->getRoutes();
    }

    /**
     * Разослать событие всем подходящим слушателям.
     *
     * Слушатели вызываются по очереди в порядке объявления маршрутов. Каждый получает
     * копию события с параметрами своего маршрута в атрибутах и проходит через свои middleware.
     * Исключение в слушателе или его middleware попадает в отчёт и в обработчик ошибок,
     * а дальше всё решает стратегия (setErrorStrategy()): по умолчанию остальные слушатели продолжают работу.
     */
    public function dispatch(EventInterface $event): DispatchReport
    {
        return $this->dispatcher->dispatch($event, $this->match($event->getName()), array_reverse($this->middleware));
    }

    /**
     * Воркер: забирать события из источника и рассылать их по одному — аналог `$app->run()` в Slim.
     *
     * Возвращает, сколько событий обработано, когда источник закончился.
     * После каждой рассылки вызывает `$afterDispatch` с отчётом — например, чтобы залогировать ошибки.
     * Исключение из dispatch() (middleware роутера, обработчик ошибок, стратегия Throw)
     * останавливает воркер и выходит наружу: процесс перезапустит менеджер процессов.
     *
     * ```php
     * $events->run(new AmqpEventSource($channel, queue: 'orders'));
     * ```
     *
     * @param (Closure(DispatchReport): void)|null $afterDispatch
     */
    public function run(EventSourceInterface $source, ?Closure $afterDispatch = null): int
    {
        $processed = 0;

        foreach ($source->events() as $event) {
            $report = $this->dispatch($event);
            ++$processed;

            if ($afterDispatch !== null) {
                $afterDispatch($report);
            }
        }

        return $processed;
    }

    /**
     * Таблица маршрутов: собирается при первом обращении и после любых изменений в маршрутах.
     */
    private function table(): RouteTable
    {
        if ($this->table === null || $this->tableRevision !== $this->routes->getRevision()) {
            $this->table = $this->builder->build($this->routes, $this->prefix);
            $this->tableRevision = $this->routes->getRevision();
        }

        return $this->table;
    }
}
