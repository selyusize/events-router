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
use Selyusize\EventsRouter\Exception\InvalidRoute;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Locale\Messages;
use Selyusize\EventsRouter\Routing\CompiledRoute;
use Selyusize\EventsRouter\Routing\Revision;
use Selyusize\EventsRouter\Routing\Route;
use Selyusize\EventsRouter\Routing\RouteAssert;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Routing\RouteMatch;
use Selyusize\EventsRouter\Routing\RouteTable;
use Selyusize\EventsRouter\Service\Dispatcher;
use Selyusize\EventsRouter\Service\RouteCache;
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
 * $events->loadRoutes(require __DIR__ . '/events.php');
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

    /**
     * Middleware роутера в порядке выполнения: собирается при первой рассылке после add().
     *
     * @var list<class-string<MiddlewareInterface>|MiddlewareInterface>|null
     */
    private ?array $middlewareInOrder = null;

    private ?RouteTable $table = null;

    private int $tableRevision = -1;

    /**
     * Идёт loadRoutes(): с включённым кэшем маршруты можно объявлять только в это время.
     */
    private bool $loading = false;

    private bool $loaded = false;

    /**
     * Таблица взята из кэша: объявлений в группах нет, пересобирать её не из чего.
     */
    private bool $fromCache = false;

    /**
     * @internal используйте EventRouterFactory::create()
     */
    public function __construct(
        private readonly RouteGroup $routes,
        private readonly Revision $revision,
        private readonly RouteTableBuilder $builder,
        private Dispatcher $dispatcher,
        private readonly ?RouteCache $cache = null,
    ) {}

    /**
     * Подключить маршруты — обычно файл маршрутов: `$events->loadRoutes(require __DIR__ . '/events.php')`.
     *
     * Без кэша просто вызывает `$routes($this)`. С кэшем (`route_cache_file` в EventRouterFactory::create()):
     *
     * - файла кэша нет — вызывает `$routes`, собирает таблицу и записывает её в файл;
     * - файл есть — берёт таблицу и middleware роутера из него, а `$routes` не вызывает вовсе:
     *   не выполняется файл маршрутов и не загружаются классы слушателей.
     *
     * С кэшем маршруты объявляются только внутри `$routes`, а loadRoutes() вызывается один раз.
     * Кэш не обновляется сам: после изменения маршрутов файл кэша нужно удалить.
     *
     * @param callable(EventRouter): void $routes
     *
     * @throws InvalidRoute если с кэшем loadRoutes() вызван второй раз или middleware добавлен объектом
     */
    public function loadRoutes(callable $routes): self
    {
        if ($this->cache !== null && $this->loaded) {
            throw InvalidRoute::because(Messages::translate('с кэшем маршрутов loadRoutes() вызывается один раз: подключите все маршруты из одного файла'));
        }

        $this->loaded = true;
        $cached = $this->cache?->load();

        if ($cached !== null) {
            [$this->table, $middleware] = $cached;
            $this->middleware = [...$this->middleware, ...$middleware];
            $this->middlewareInOrder = null;
            $this->fromCache = true;

            return $this;
        }

        // Middleware роутера из файла маршрутов тоже попадут в кэш: при чтении из кэша файл не выполняется
        $before = \count($this->middleware);
        $this->loading = true;

        try {
            $routes($this);
        } finally {
            $this->loading = false;
        }

        $this->cache?->save($this->table(), \array_slice($this->middleware, $before));

        return $this;
    }

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
        $this->assertDeclaring();

        $this->table = $this->builder->build($this->routes, $prefix);
        $this->tableRevision = $this->revision->get();
        $this->prefix = $prefix;

        return $this;
    }

    #[Override]
    public function listen(string $pattern, string $listener): Route
    {
        $this->assertDeclaring();

        return $this->routes->listen($pattern, $listener);
    }

    #[Override]
    public function group(string $prefix, callable $routes): RouteGroup
    {
        $this->assertDeclaring();

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
        $this->middlewareInOrder = null;

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
        return $this->dispatcher->dispatch($event, $this->table(), $this->middlewareInOrder ??= array_reverse($this->middleware));
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
        if ($this->table === null || (!$this->fromCache && $this->tableRevision !== $this->revision->get())) {
            $this->table = $this->builder->build($this->routes, $this->prefix);
            $this->tableRevision = $this->revision->get();
        }

        return $this->table;
    }

    /**
     * С кэшем маршруты, объявленные вне loadRoutes(), в проде пропали бы: файл кэша есть, и
     * loadRoutes() не выполняет ничего. Поэтому такие объявления — ошибка сразу, и на разработке тоже.
     *
     * @throws InvalidRoute
     */
    private function assertDeclaring(): void
    {
        if ($this->cache !== null && !$this->loading) {
            throw InvalidRoute::because(Messages::translate('с кэшем маршрутов (route_cache_file) маршруты объявляются только внутри loadRoutes()'));
        }
    }
}
