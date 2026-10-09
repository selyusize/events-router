<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Dispatch\DispatchReport;
use Selyusize\EventsRouter\Dispatch\ErrorStrategy;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Source\InMemoryEventSource;
use Selyusize\EventsRouter\Tests\Fixture\Journal;
use Selyusize\EventsRouter\Tests\Fixture\Scripted\ScriptedListener;

/**
 * @internal
 */
final class RunTest extends TestCase
{
    protected function setUp(): void
    {
        Journal::reset();
        ScriptedListener::reset();
    }

    public function testDispatchesEveryEventAndReturnsCount(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.#', self::recorder());

        $processed = $events->run(new InMemoryEventSource(new Event('order.created'), new Event('order.42.paid'), new Event('catalog.updated')));

        self::assertSame(3, $processed);
        self::assertSame(['order.created', 'order.42.paid'], Journal::$entries);
    }

    public function testCallsAfterDispatchWithEachReport(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.{order_id}.paid', self::recorder());

        $reports = [];
        $events->run(
            new InMemoryEventSource(new Event('order.1.paid'), new Event('order.2.paid')),
            static function (DispatchReport $report) use (&$reports): void {
                $reports[] = $report;
            },
        );

        self::assertCount(2, $reports);
        self::assertSame('order.1.paid', $reports[0]->getEvent()->getName());
        self::assertSame('2', $reports[1]->getListeners()[0]->getEvent()->getAttribute('order_id'));
    }

    public function testEventsAddedByListenersAreProcessedInTheSameRun(): void
    {
        $source = new InMemoryEventSource(new Event('order.created'));

        $events = EventRouterFactory::create();
        $events->listen('order.created', ScriptedListener::define(static function (EventInterface $event) use ($source): void {
            Journal::write($event->getName());
            $source->push(new Event('stock.reserved'));
        }));
        $events->listen('stock.reserved', self::recorder());

        self::assertSame(2, $events->run($source));
        self::assertSame(['order.created', 'stock.reserved'], Journal::$entries);
    }

    public function testExceptionFromDispatchStopsWorker(): void
    {
        $source = new InMemoryEventSource(new Event('order.broken'), new Event('order.next'));

        $events = EventRouterFactory::create()->setErrorStrategy(ErrorStrategy::Throw);
        $events->listen('order.broken', ScriptedListener::define(static function (): void {
            throw new RuntimeException('сбой');
        }));
        $events->listen('order.next', self::recorder());

        try {
            $events->run($source);
            self::fail('Ожидалось исключение');
        } catch (RuntimeException $error) {
            self::assertSame('сбой', $error->getMessage());
        }

        self::assertSame([], Journal::$entries);
        self::assertSame(1, $source->count(), 'необработанное событие осталось в очереди');
    }

    public function testEmptySourceProcessesNothing(): void
    {
        self::assertSame(0, EventRouterFactory::create()->run(new InMemoryEventSource()));
    }

    /**
     * @return class-string<ListenerInterface>
     */
    private static function recorder(): string
    {
        return ScriptedListener::define(static function (EventInterface $event): void {
            Journal::write($event->getName());
        });
    }
}
