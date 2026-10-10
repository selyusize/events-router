<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

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
        echo '[', $this->clock->now(), '] письмо: ', $text, PHP_EOL;
    }
}

// Слушатель статичный: зависимости берёт из контейнера сам
final class SendConfirmationEmail implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        Container::get(Mailer::class)->send('заказ ' . $event->getAttribute('order_id') . ' оформлен');
    }
}

// Middleware получает зависимости через конструктор: контейнер создаст его сам
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

$events = EventRouterFactory::create();   // без контейнера проекта — PHP-DI с автосвязыванием

$events->listen('shop.order.{order_id}.created', SendConfirmationEmail::class)
    ->add(StampTime::class);              // Clock в конструктор подставит контейнер

$events->dispatch(new Event('shop.order.42.created'));
// --8<-- [end:example]
