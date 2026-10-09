<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\Source\InMemoryEventSource;

/**
 * @internal
 */
final class InMemoryEventSourceTest extends TestCase
{
    public function testYieldsEventsInOrderAndEmptiesQueue(): void
    {
        $source = new InMemoryEventSource(new Event('first'), new Event('second'));
        $source->push(new Event('third'));

        self::assertSame(3, $source->count());
        self::assertSame(['first', 'second', 'third'], self::names($source->events()));
        self::assertSame(0, $source->count());
        self::assertSame([], self::names($source->events()), 'повторный обход пуст');
    }

    public function testEventsPushedDuringIterationAreYieldedToo(): void
    {
        $source = new InMemoryEventSource(new Event('order.created'));
        $names = [];

        foreach ($source->events() as $event) {
            $names[] = $event->getName();

            if ($event->getName() === 'order.created') {
                $source->push(new Event('order.reserved'));
            }
        }

        self::assertSame(['order.created', 'order.reserved'], $names);
    }

    /**
     * @param iterable<EventInterface> $events
     *
     * @return list<string>
     */
    private static function names(iterable $events): array
    {
        $names = [];

        foreach ($events as $event) {
            $names[] = $event->getName();
        }

        return $names;
    }
}
