<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class ReserveStock implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo '    резерв товара для заказа ', $event->getAttribute('order_id'), PHP_EOL;
    }
}

final class AccrueBonuses implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        throw new RuntimeException('сервис бонусов недоступен');
    }
}

// --8<-- [start:example]
use Selyusize\EventsRouter\Dispatch\DispatchReport;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Source\InMemoryEventSource;

$events = EventRouterFactory::create();

$events->listen('order.{order_id}.created', ReserveStock::class);
$events->listen('order.{order_id}.paid', AccrueBonuses::class);

$source = new InMemoryEventSource(
    new Event('order.41.created'),
    new Event('order.42.paid'),
    new Event('catalog.updated'),
);

$processed = $events->run($source, static function (DispatchReport $report): void {
    $status = match (true) {
        !$report->hasListeners() => 'слушателей нет',
        $report->hasFailures() => 'ошибка: ' . $report->getFailures()[0]->getError()?->getMessage(),
        default => 'ок',
    };

    echo $report->getEvent()->getName(), ' — ', $status, PHP_EOL;
});

echo 'обработано событий: ', $processed, PHP_EOL;
// --8<-- [end:example]
