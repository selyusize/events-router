<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Dispatch;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Selyusize\EventsRouter\Contract\Core\EventHandlerInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Dispatch\ListenerReport;
use Selyusize\EventsRouter\Dispatch\ListenerStatus;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Exception\UnresolvableHandler;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Tests\Fixture\ArrayContainer;
use Selyusize\EventsRouter\Tests\Fixture\Journal;
use Selyusize\EventsRouter\Tests\Fixture\NotAHandler;
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
        RecordingMiddleware::$instances = 0;
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
        self::assertSame([ListenerStatus::Handled, ListenerStatus::Handled, ListenerStatus::Handled], self::statuses($report->getListeners()));
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
        self::assertSame([ListenerStatus::Handled, ListenerStatus::Failed, ListenerStatus::Handled], self::statuses($report->getListeners()));
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

        self::assertSame([ListenerStatus::Failed, ListenerStatus::Handled], self::statuses($report->getListeners()));
        self::assertSame(['→ broken', 'second {}'], Journal::$entries);
    }

    public function testMiddlewareThatDoesNotCallNextSkipsListener(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.paid', self::listener('blocked'))->add(self::middleware('guard', callNext: false));
        $events->listen('order.paid', self::listener('second'));

        $report = $events->dispatch(new Event('order.paid'));

        self::assertSame(['→ guard', '← guard', 'second {}'], Journal::$entries);
        self::assertSame([ListenerStatus::Skipped, ListenerStatus::Handled], self::statuses($report->getListeners()));
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
        self::assertSame([ListenerStatus::Skipped, ListenerStatus::Skipped], self::statuses($report->getListeners()));
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

    public function testCreatesMiddlewareWithoutContainerAndReusesInstance(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.paid', RecordingListener::class)->add(RecordingMiddleware::class);

        $events->dispatch(new Event('order.paid'));
        $events->dispatch(new Event('order.paid'));

        self::assertSame(['RecordingMiddleware', 'RecordingListener order.paid', 'RecordingMiddleware', 'RecordingListener order.paid'], Journal::$entries);
        self::assertSame(1, RecordingMiddleware::$instances);
    }

    public function testTakesMiddlewareFromContainer(): void
    {
        $events = EventRouterFactory::create(new ArrayContainer([RecordingMiddleware::class => self::middleware('from-container')]));
        $events->listen('order.paid', RecordingListener::class)->add(RecordingMiddleware::class);

        $events->dispatch(new Event('order.paid'));

        self::assertSame(['→ from-container', 'RecordingListener order.paid', '← from-container'], Journal::$entries);
        self::assertSame(0, RecordingMiddleware::$instances);
    }

    public function testFallsBackToNewWhenContainerDoesNotKnowMiddleware(): void
    {
        $events = EventRouterFactory::create(new ArrayContainer([]));
        $events->listen('order.paid', RecordingListener::class)->add(RecordingMiddleware::class);

        $events->dispatch(new Event('order.paid'));

        self::assertSame(['RecordingMiddleware', 'RecordingListener order.paid'], Journal::$entries);
    }

    public function testUnknownListenerClassFailsOnlyThisListener(): void
    {
        /** @var class-string<ListenerInterface> $missing */
        $missing = 'App\Missing\Listener';

        $error = $this->failedListenerError($missing);

        self::assertStringContainsString('Слушатель "App\Missing\Listener" нельзя использовать: класс не найден', $error->getMessage());
    }

    public function testListenerClassWithoutInterfaceFails(): void
    {
        /** @var class-string<ListenerInterface> $class */
        $class = NotAHandler::class;

        self::assertStringContainsString('не реализует ' . ListenerInterface::class, $this->failedListenerError($class)->getMessage());
    }

    public function testInvalidMiddlewareFailsOnlyItsListener(): void
    {
        /** @var class-string<MiddlewareInterface> $class */
        $class = NotAHandler::class;

        $events = EventRouterFactory::create();
        $events->listen('order.paid', self::listener('guarded'))->add($class);
        $events->listen('order.paid', self::listener('next'));

        $report = $events->dispatch(new Event('order.paid'));
        $error = $report->getListeners()[0]->getError();

        self::assertSame(['next {}'], Journal::$entries);
        self::assertInstanceOf(UnresolvableHandler::class, $error);
        self::assertStringContainsString('Middleware "' . NotAHandler::class . '" нельзя использовать: класс не реализует', $error->getMessage());
    }

    public function testContainerReturningWrongMiddlewareTypeFails(): void
    {
        $events = EventRouterFactory::create(new ArrayContainer([RecordingMiddleware::class => 'не объект']));
        $events->listen('order.paid', self::listener('guarded'))->add(RecordingMiddleware::class);

        $error = $events->dispatch(new Event('order.paid'))->getListeners()[0]->getError();

        self::assertInstanceOf(UnresolvableHandler::class, $error);
        self::assertStringContainsString('контейнер вернул string', $error->getMessage());
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
     * Слушатель упал, следующий отработал; вернуть ошибку упавшего.
     *
     * @param class-string<ListenerInterface> $listener
     */
    private function failedListenerError(string $listener): UnresolvableHandler
    {
        $events = EventRouterFactory::create();
        $events->listen('order.paid', $listener);
        $events->listen('order.paid', self::listener('next'));

        $report = $events->dispatch(new Event('order.paid'));
        $error = $report->getListeners()[0]->getError();

        self::assertSame([ListenerStatus::Failed, ListenerStatus::Handled], self::statuses($report->getListeners()));
        self::assertInstanceOf(UnresolvableHandler::class, $error);

        return $error;
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

            public function process(EventInterface $event, EventHandlerInterface $next): void
            {
                Journal::write('→ ' . $this->name);

                if ($this->error !== null) {
                    throw $this->error;
                }

                foreach ($this->attributes as $key => $value) {
                    $event = $event->withAttribute($key, $value);
                }

                if ($this->callNext) {
                    $next->handle($event);
                }

                Journal::write('← ' . $this->name);
            }
        };
    }

    /**
     * @param list<ListenerReport> $reports
     *
     * @return list<ListenerStatus>
     */
    private static function statuses(array $reports): array
    {
        return array_map(static fn (ListenerReport $report): ListenerStatus => $report->getStatus(), $reports);
    }
}
