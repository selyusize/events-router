<?php

declare(strict_types=1);

// Bitrix classes here are stubs from tests/Stub: they behave like the Bitrix core, without it
require __DIR__ . '/../bootstrap.php';

$GLOBALS['APPLICATION'] = new CMain();

// --8<-- [start:listeners]
use Selyusize\EventsRouter\Bitrix\BitrixEvent;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Webmozart\Assert\Assert;

// Old API: fields come by reference, you can fix them or cancel saving
final class ValidateProduct implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        $bitrix = $event->getPayload();
        Assert::isInstanceOf($bitrix, BitrixEvent::class);

        $name = trim((string)($bitrix->getFields()['NAME'] ?? ''));

        if ($name === '') {
            $bitrix->cancel('Product name is required');

            return;
        }

        $bitrix->setField('NAME', $name);
    }
}

// D7: the data is in a Bitrix\Main\Event object
final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        $bitrix = $event->getPayload();
        Assert::isInstanceOf($bitrix, BitrixEvent::class);

        echo 'Order ', $bitrix->getD7Event()?->getParameter('ORDER_ID'), ' paid', PHP_EOL;
    }
}
// --8<-- [end:listeners]

// --8<-- [start:routes]
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\Routing\RouteGroup;

$routes = static function (EventRouter $events): void {
    $events->group('bitrix', static function (RouteGroup $bitrix): void {
        $bitrix->listen('iblock.OnBeforeIBlockElementUpdate', ValidateProduct::class);
        $bitrix->listen('sale.OnSaleOrderPaid', MarkOrderPaid::class);
    });
};
// --8<-- [end:routes]

// --8<-- [start:attach]
use Bitrix\Main\EventManager;
use Selyusize\EventsRouter\Bitrix\BitrixEventBridge;
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create();
$routes($events);

BitrixEventBridge::attach(EventManager::getInstance(), $events);
// --8<-- [end:attach]

// --8<-- [start:fire]
use Bitrix\Main\Event as D7Event;

// This is how Bitrix fires an old-style event before saving an infoblock element
$arFields = ['NAME' => '  Kettle  '];

foreach (GetModuleEvents('iblock', 'OnBeforeIBlockElementUpdate', true) as $arEvent) {
    ExecuteModuleEventEx($arEvent, [&$arFields]);
}

echo 'Name after the listener: "', $arFields['NAME'], '"', PHP_EOL;

// Empty name: the listener cancels saving
$arFields = ['NAME' => ''];

foreach (GetModuleEvents('iblock', 'OnBeforeIBlockElementUpdate', true) as $arEvent) {
    if (ExecuteModuleEventEx($arEvent, [&$arFields]) === false) {
        echo 'Saving cancelled: ', $APPLICATION->GetException()->GetString(), PHP_EOL;
    }
}

// And this is a D7 event
(new D7Event('sale', 'OnSaleOrderPaid', ['ORDER_ID' => 42]))->send();
// --8<-- [end:fire]
