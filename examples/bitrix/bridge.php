<?php

declare(strict_types=1);

// Классы Bitrix здесь — заглушки из tests/Stub: ведут себя как ядро Bitrix, но без него
require __DIR__ . '/../../vendor/autoload.php';

$GLOBALS['APPLICATION'] = new CMain();

// --8<-- [start:listeners]
use Selyusize\EventsRouter\Bitrix\BitrixEvent;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Webmozart\Assert\Assert;

// Старый API: поля приходят по ссылке, их можно поправить или отменить сохранение
final class ValidateProduct implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        $bitrix = $event->getPayload();
        Assert::isInstanceOf($bitrix, BitrixEvent::class);

        $name = trim((string)($bitrix->getFields()['NAME'] ?? ''));

        if ($name === '') {
            $bitrix->cancel('Название товара обязательно');

            return;
        }

        $bitrix->setField('NAME', $name);
    }
}

// D7: данные — в объекте Bitrix\Main\Event
final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        $bitrix = $event->getPayload();
        Assert::isInstanceOf($bitrix, BitrixEvent::class);

        echo 'Заказ ', $bitrix->getD7Event()?->getParameter('ORDER_ID'), ' оплачен', PHP_EOL;
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

// Так Bitrix вызывает старое событие перед сохранением элемента инфоблока
$arFields = ['NAME' => '  Чайник  '];

foreach (GetModuleEvents('iblock', 'OnBeforeIBlockElementUpdate', true) as $arEvent) {
    ExecuteModuleEventEx($arEvent, [&$arFields]);
}

echo 'Название после слушателя: "', $arFields['NAME'], '"', PHP_EOL;

// Пустое название — слушатель отменяет сохранение
$arFields = ['NAME' => ''];

foreach (GetModuleEvents('iblock', 'OnBeforeIBlockElementUpdate', true) as $arEvent) {
    if (ExecuteModuleEventEx($arEvent, [&$arFields]) === false) {
        echo 'Сохранение отменено: ', $APPLICATION->GetException()->GetString(), PHP_EOL;
    }
}

// А так — событие D7
(new D7Event('sale', 'OnSaleOrderPaid', ['ORDER_ID' => 42]))->send();
// --8<-- [end:fire]
