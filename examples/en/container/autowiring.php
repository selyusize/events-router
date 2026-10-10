<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// --8<-- [start:classes]
use Selyusize\EventsRouter\Container\Container;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

final class Clock
{
    public function now(): string
    {
        return '14:03';
    }
}

final class Mailer
{
    public function __construct(private readonly Clock $clock) {}

    public function send(string $text): void
    {
        echo '[', $this->clock->now(), '] email: ', $text, PHP_EOL;
    }
}

// The listener is static: it takes dependencies from the container itself
final class SendConfirmationEmail implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        Container::get(Mailer::class)->send('order ' . $event->getAttribute('order_id') . ' created');
    }
}

// Middleware gets dependencies through the constructor: the container creates it
final class StampTime implements MiddlewareInterface
{
    public function __construct(private readonly Clock $clock) {}

    public function process(EventInterface $event, Closure $next): void
    {
        $next($event->withAttribute('received_at', $this->clock->now()));
    }
}
// --8<-- [end:classes]

// --8<-- [start:example]
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create();   // no project container — PHP-DI with autowiring

$events->listen('shop.order.{order_id}.created', SendConfirmationEmail::class)
    ->add(StampTime::class);              // the container injects Clock into the constructor

$events->dispatch(new Event('shop.order.42.created'));
// --8<-- [end:example]
