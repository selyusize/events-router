<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo '    MarkOrderPaid', PHP_EOL;
    }
}

final class AccrueBonuses implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        throw new RuntimeException('сервис бонусов недоступен');
    }
}

final class SendReceipt implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo '    SendReceipt', PHP_EOL;
    }
}

// --8<-- [start:handler]
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;

final class EchoErrorHandler implements ErrorHandlerInterface
{
    public function handle(Throwable $error, EventInterface $event, string $listener): void
    {
        echo '    ошибка: ', $listener, ' — ', $error->getMessage(), PHP_EOL;
    }
}
// --8<-- [end:handler]

// --8<-- [start:strategies]
use Selyusize\EventsRouter\Dispatch\ErrorStrategy;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;

foreach ([ErrorStrategy::Continue, ErrorStrategy::Stop, ErrorStrategy::Throw] as $strategy) {
    echo $strategy->name, ':', PHP_EOL;

    $events = EventRouterFactory::create()
        ->setErrorHandler(EchoErrorHandler::class)
        ->setErrorStrategy($strategy);

    $events->listen('order.paid', MarkOrderPaid::class);
    $events->listen('order.paid', AccrueBonuses::class);
    $events->listen('order.paid', SendReceipt::class);

    try {
        $report = $events->dispatch(new Event('order.paid'));

        foreach ($report->getListeners() as $listener) {
            echo '    ', $listener->getListener(), ': ', $listener->getStatus()->name, PHP_EOL;
        }
    } catch (RuntimeException $error) {
        echo '    исключение из dispatch(): ', $error->getMessage(), PHP_EOL;
    }
}
// --8<-- [end:strategies]
