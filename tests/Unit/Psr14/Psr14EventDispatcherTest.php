<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Psr14;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Exception\InvalidEventName;
use Selyusize\EventsRouter\Exception\UnmappableEvent;
use Selyusize\EventsRouter\Psr14\Psr14EventDispatcher;
use Selyusize\EventsRouter\Tests\Fixture\Journal;
use Selyusize\EventsRouter\Tests\Fixture\Scripted\ScriptedListener;
use stdClass;

/**
 * @internal
 */
final class Psr14EventDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        Journal::reset();
        ScriptedListener::reset();
    }

    public function testImplementsPsr14(): void
    {
        self::assertInstanceOf(EventDispatcherInterface::class, new Psr14EventDispatcher(EventRouterFactory::create()));
    }

    public function testDispatchesLibraryEventAsIsAndReturnsIt(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.{order_id}.paid', self::recorder());
        $event = new Event('order.42.paid');

        $returned = (new Psr14EventDispatcher($events))->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame(['order.42.paid 42'], Journal::$entries);
    }

    public function testMapsObjectToTopicAndPassesItAsPayload(): void
    {
        $order = new stdClass();
        $order->id = 42;

        $events = EventRouterFactory::create();
        $events->listen('order.{order_id}.paid', ScriptedListener::define(static function (EventInterface $event) use ($order): void {
            Journal::write($event->getPayload() === $order ? 'тот же объект' : 'другой объект');
        }));

        $dispatcher = new Psr14EventDispatcher($events, static fn (object $event): string => 'order.' . (string)($event->id ?? '') . '.paid');

        self::assertSame($order, $dispatcher->dispatch($order));
        self::assertSame(['тот же объект'], Journal::$entries);
    }

    public function testStoppableObjectStopsPropagation(): void
    {
        $event = new class implements StoppableEventInterface {
            public bool $stopped = false;

            public function isPropagationStopped(): bool
            {
                return $this->stopped;
            }
        };

        $events = EventRouterFactory::create();
        $events->listen('order.paid', ScriptedListener::define(static function (EventInterface $event): void {
            Journal::write('first');
            $payload = $event->getPayload();
            \assert(\is_object($payload) && property_exists($payload, 'stopped'));
            $payload->stopped = true;
        }));
        $events->listen('order.paid', self::recorder());

        (new Psr14EventDispatcher($events, static fn (): string => 'order.paid'))->dispatch($event);

        self::assertSame(['first'], Journal::$entries);
    }

    public function testObjectWithoutMapperIsRejected(): void
    {
        $this->expectException(UnmappableEvent::class);
        $this->expectExceptionMessage('Неизвестно имя события для объекта stdClass');

        (new Psr14EventDispatcher(EventRouterFactory::create()))->dispatch(new stdClass());
    }

    public function testInvalidMappedNameIsRejected(): void
    {
        $this->expectException(InvalidEventName::class);

        (new Psr14EventDispatcher(EventRouterFactory::create(), static fn (): string => 'order..paid'))->dispatch(new stdClass());
    }

    /**
     * @return class-string<ListenerInterface>
     */
    private static function recorder(): string
    {
        return ScriptedListener::define(static function (EventInterface $event): void {
            Journal::write($event->getName() . ' ' . (string)$event->getAttribute('order_id', ''));
        });
    }
}
