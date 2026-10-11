<?php

declare(strict_types=1);

// Классы Bitrix здесь — заглушки из tests/Stub: ведут себя как ядро Bitrix, но без него
require __DIR__ . '/../../vendor/autoload.php';

// --8<-- [start:listeners]
use Selyusize\EventsRouter\Bitrix\BitrixEvent;
use Selyusize\EventsRouter\Bitrix\BitrixEventBridge;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Event;
use Webmozart\Assert\Assert;

// Переводит событие Bitrix на язык домена: дальше бизнес-логика о Bitrix не знает
final class TranslateOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        $bitrix = $event->getPayload();
        Assert::isInstanceOf($bitrix, BitrixEvent::class);

        $orderId = (int)$bitrix->getD7Event()?->getParameter('ORDER_ID');

        BitrixEventBridge::dispatch(new Event('shop.order.' . $orderId . '.paid'));
    }
}

final class SendReceipt implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo 'Чек по заказу ', $event->getAttribute('order_id'), ' отправлен', PHP_EOL;
    }
}

final class AccrueBonus implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        echo 'Бонусы за заказ ', $event->getAttribute('order_id'), ' начислены', PHP_EOL;
    }
}
// --8<-- [end:listeners]

// --8<-- [start:routes]
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\Routing\RouteGroup;

$routes = static function (EventRouter $events): void {
    // События Bitrix
    $events->group('bitrix', static function (RouteGroup $bitrix): void {
        $bitrix->listen('sale.OnSaleOrderPaid', TranslateOrderPaid::class);
    });

    // Свои события проекта
    $events->group('shop', static function (RouteGroup $shop): void {
        $shop->listen('order.{order_id:\d+}.paid', SendReceipt::class);
        $shop->listen('order.{order_id:\d+}.paid', AccrueBonus::class);
    });
};
// --8<-- [end:routes]

use Bitrix\Main\EventManager;
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create();
$routes($events);

BitrixEventBridge::attach(EventManager::getInstance(), $events);

// --8<-- [start:fire]
use Bitrix\Main\Event as D7Event;

// Bitrix сообщает об оплате — слушатель переводит событие в shop.order.42.paid
(new D7Event('sale', 'OnSaleOrderPaid', ['ORDER_ID' => 42]))->send();

// Своё событие из компонента, контроллера или агента — без события Bitrix
$report = BitrixEventBridge::dispatch(new Event('shop.order.43.paid'));

echo 'Слушателей: ', count($report->getListeners()), PHP_EOL;
// --8<-- [end:fire]
