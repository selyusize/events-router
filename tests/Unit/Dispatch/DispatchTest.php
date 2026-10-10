<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Dispatch;

use Closure;
use DI\Container as PhpDiContainer;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Selyusize\EventsRouter\Container\Container;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Dispatch\ListenerReport;
use Selyusize\EventsRouter\Dispatch\ListenerStatusEnum;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Tests\Fixture\Journal;
use Selyusize\EventsRouter\Tests\Fixture\MarkingMiddleware;
use Selyusize\EventsRouter\Tests\Fixture\RecordingListener;
use Selyusize\EventsRouter\Tests\Fixture\RecordingMiddleware;
use Selyusize\EventsRouter\Tests\Fixture\Scripted\ScriptedListener;

/**
 * @internal
 */
final class DispatchTest extends TestCase
{
    protected function setUp(): void
    {
        Journal::reset();
        ScriptedListener::reset();
    }

    protected function tearDown(): void
    {
        // Фасад контейнера глобальный: контейнер одного теста не должен попасть в другой
        Container::set(new PhpDiContainer());
    }

    public function testCallsListenersInDeclarationOrderWithOwnParameters(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.#', self::listener('all'));
        $events->group('order', static function (RouteGroup $group): void {
            $group->listen('{order_id}.paid', self::listener('paid'));
            $group->listen('{order_id}.cancelled', self::listener('cancelled'));
        });
        $events->listen('order.{id}.{status}', self::listener('status'));

        $report = $events->dispatch(new Event('order.42.paid'));

        self::assertSame([
            'all {}',
            'paid {"order_id":"42"}',
            'status {"id":"42","status":"paid"}',
        ], Journal::$entries);
        self::assertSame([ListenerStatusEnum::Handled, ListenerStatusEnum::Handled, ListenerStatusEnum::Handled], self::statuses($report->getListeners()));
        self::assertSame(['order_id' => '42'], $report->getListeners()[1]->getEvent()->getAttributes());
        self::assertSame([], $report->getEvent()->getAttributes(), 'исходное событие не меняется');
    }

    public function testRouterMiddlewareRunsOncePerEventAndRouteMiddlewarePerListener(): void
    {
        $events = EventRouterFactory::create();
        $events->add(self::middleware('router-inner'))->add(self::middleware('router-outer'));

        $events->group('order', static function (RouteGroup $group): void {
            $group->listen('paid', self::listener('first'))->add(self::middleware('route'));
            $group->listen('paid', self::listener('second'));
        })->add(self::middleware('group'));

        $events->dispatch(new Event('order.paid'));

        self::assertSame([
            '→ router-outer',
            '→ router-inner',
            '→ group',
            '→ route',
            'first {}',
            '← route',
            '← group',
            '→ group',
            'second {}',
            '← group',
            '← router-inner',
            '← router-outer',
        ], Journal::$entries);
    }

    public function testFailingListenerDoesNotStopOthers(): void
    {
        $error = new RuntimeException('почта недоступна');

        $events = EventRouterFactory::create();
        $events->listen('order.paid', self::listener('first'));
        $events->listen('order.paid', self::listener('broken', $error));
        $events->listen('order.paid', self::listener('third'));

        $report = $events->dispatch(new Event('order.paid'));

        self::assertSame(['first {}', 'broken {}', 'third {}'], Journal::$entries);
        self::assertSame([ListenerStatusEnum::Handled, ListenerStatusEnum::Failed, ListenerStatusEnum::Handled], self::statuses($report->getListeners()));
        self::assertTrue($report->hasFailures());
        self::assertCount(1, $report->getFailures());
        self::assertSame($error, $report->getFailures()[0]->getError());
        self::assertNull($report->getListeners()[0]->getError());
    }

    public function testExceptionInRouteMiddlewareFailsOnlyItsListener(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.paid', self::listener('first'))->add(self::middleware('broken', error: new RuntimeException('сбой')));
        $events->listen('order.paid', self::listener('second'));

        $report = $events->dispatch(new Event('order.paid'));

        self::assertSame([ListenerStatusEnum::Failed, ListenerStatusEnum::Handled], self::statuses($report->getListeners()));
        self::assertSame(['→ broken', 'second {}'], Journal::$entries);
    }

    public function testMiddlewareThatDoesNotCallNextSkipsListener(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.paid', self::listener('blocked'))->add(self::middleware('guard', callNext: false));
        $events->listen('order.paid', self::listener('second'));

        $report = $events->dispatch(new Event('order.paid'));

        self::assertSame(['→ guard', '← guard', 'second {}'], Journal::$entries);
        self::assertSame([ListenerStatusEnum::Skipped, ListenerStatusEnum::Handled], self::statuses($report->getListeners()));
        self::assertFalse($report->hasFailures());
    }

    public function testRouterMiddlewareThatDoesNotCallNextSkipsAllListeners(): void
    {
        $events = EventRouterFactory::create();
        $events->add(self::middleware('guard', callNext: false));
        $events->listen('order.{order_id}', self::listener('first'));
        $events->listen('order.#', self::listener('second'));

        $report = $events->dispatch(new Event('order.42'));

        self::assertSame(['→ guard', '← guard'], Journal::$entries);
        self::assertSame([ListenerStatusEnum::Skipped, ListenerStatusEnum::Skipped], self::statuses($report->getListeners()));
        self::assertSame(['order_id' => '42'], $report->getListeners()[0]->getEvent()->getAttributes());
    }

    public function testRouterMiddlewareRunsEvenWithoutListeners(): void
    {
        $events = EventRouterFactory::create();
        $events->add(self::middleware('log'));
        $events->listen('order.paid', self::listener('paid'));

        $report = $events->dispatch(new Event('catalog.updated'));

        self::assertSame(['→ log', '← log'], Journal::$entries);
        self::assertFalse($report->hasListeners());
        self::assertSame([], $report->getListeners());
    }

    public function testExceptionInRouterMiddlewareIsThrown(): void
    {
        $events = EventRouterFactory::create();
        $events->add(self::middleware('broken', error: new RuntimeException('сбой трассировки')));
        $events->listen('order.paid', self::listener('paid'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('сбой трассировки');

        $events->dispatch(new Event('order.paid'));
    }

    public function testMiddlewareCanPassModifiedEventAndParametersOverrideAttributes(): void
    {
        $events = EventRouterFactory::create();
        $events->add(self::middleware('router', attributes: ['trace_id' => 't-1', 'order_id' => 'from-router']));
        $events->listen('order.{order_id}.paid', self::listener('paid'))->add(self::middleware('route', attributes: ['user_id' => '7']));

        $events->dispatch(new Event('order.42.paid'));

        self::assertSame(['→ router', '→ route', 'paid {"trace_id":"t-1","order_id":"42","user_id":"7"}', '← route', '← router'], Journal::$entries);
    }

    public function testCallsStaticListenerWithoutCreatingIt(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.paid', RecordingListener::class);

        $events->dispatch(new Event('order.paid'));

        self::assertSame(['RecordingListener order.paid'], Journal::$entries);
    }

    public function testCreatesMiddlewareByClassNameWithoutContainer(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.paid', RecordingListener::class)->add(RecordingMiddleware::class);

        $events->dispatch(new Event('order.paid'));

        self::assertSame(['RecordingMiddleware', 'RecordingListener order.paid'], Journal::$entries);
    }

    public function testCreatesMiddlewareWithConstructorDependencies(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.paid', RecordingListener::class)->add(MarkingMiddleware::class);

        $events->dispatch(new Event('order.paid'));

        self::assertSame(['MarkingMiddleware mark', 'RecordingListener order.paid'], Journal::$entries);
    }

    public function testTakesMiddlewareFromContainer(): void
    {
        $events = EventRouterFactory::create(new PhpDiContainer([RecordingMiddleware::class => self::middleware('from-container')]));
        $events->listen('order.paid', RecordingListener::class)->add(RecordingMiddleware::class);

        $events->dispatch(new Event('order.paid'));

        self::assertSame(['→ from-container', 'RecordingListener order.paid', '← from-container'], Journal::$entries);
    }

    public function testWorksWithAnyPsr11Container(): void
    {
        // Не PHP-DI: знает только то, что в нём зарегистрировано, и ничего не создаёт сам
        $container = new class([RecordingMiddleware::class => self::middleware('psr-11')]) implements ContainerInterface {
            /**
             * @param array<string, mixed> $entries
             */
            public function __construct(private readonly array $entries) {}

            public function get(string $id): mixed
            {
                return $this->entries[$id] ?? throw new class('нет ' . $id) extends RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return isset($this->entries[$id]);
            }
        };

        $events = EventRouterFactory::create($container);
        $events->listen('order.paid', RecordingListener::class)->add(RecordingMiddleware::class);
        $events->listen('order.paid', RecordingListener::class)->add(MarkingMiddleware::class);

        $report = $events->dispatch(new Event('order.paid'));

        self::assertSame($container, Container::getInstance());
        self::assertSame(['→ psr-11', 'RecordingListener order.paid', '← psr-11'], Journal::$entries);
        self::assertSame([ListenerStatusEnum::Handled, ListenerStatusEnum::Failed], self::statuses($report->getListeners()), 'незарегистрированный middleware — ошибка только своего слушателя');
    }

    public function testListenersGetTheSameContainerAsRouter(): void
    {
        $container = new PhpDiContainer();

        EventRouterFactory::create($container);

        self::assertSame($container, Container::getInstance());
    }

    public function testReportKeepsRouteEventAndDuration(): void
    {
        $events = EventRouterFactory::create();
        $class = self::listener('one');
        $events->listen('order.{order_id}', $class);
        $event = new Event('order.42');

        $report = $events->dispatch($event);
        $listener = $report->getListeners()[0];

        self::assertSame($event, $report->getEvent());
        self::assertSame($events->getRoutes()[0], $listener->getRoute());
        self::assertSame($class, $listener->getListener());
        self::assertSame('42', $listener->getEvent()->getAttribute('order_id'));
        self::assertGreaterThanOrEqual(0.0, $listener->getDuration());
    }

    /**
     * Статичный слушатель: пишет в журнал имя и атрибуты события, при необходимости бросает исключение.
     *
     * @return class-string<ListenerInterface>
     */
    private static function listener(string $name, ?RuntimeException $error = null): string
    {
        return ScriptedListener::define(static function (EventInterface $event) use ($name, $error): void {
            Journal::write($name . ' ' . json_encode($event->getAttributes(), JSON_FORCE_OBJECT));

            if ($error !== null) {
                throw $error;
            }
        });
    }

    /**
     * @param array<non-empty-string, string> $attributes
     */
    private static function middleware(string $name, bool $callNext = true, array $attributes = [], ?RuntimeException $error = null): MiddlewareInterface
    {
        return new class($name, $callNext, $attributes, $error) implements MiddlewareInterface {
            /**
             * @param array<non-empty-string, string> $attributes
             */
            public function __construct(
                private readonly string $name,
                private readonly bool $callNext,
                private readonly array $attributes,
                private readonly ?RuntimeException $error,
            ) {}

            public function process(EventInterface $event, Closure $next): void
            {
                Journal::write('→ ' . $this->name);

                if ($this->error !== null) {
                    throw $this->error;
                }

                foreach ($this->attributes as $key => $value) {
                    $event = $event->withAttribute($key, $value);
                }

                if ($this->callNext) {
                    $next($event);
                }

                Journal::write('← ' . $this->name);
            }
        };
    }

    /**
     * @param list<ListenerReport> $reports
     *
     * @return list<ListenerStatusEnum>
     */
    private static function statuses(array $reports): array
    {
        return array_map(static fn (ListenerReport $report): ListenerStatusEnum => $report->getStatus(), $reports);
    }
}
