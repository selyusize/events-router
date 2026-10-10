<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Bitrix;

use Bitrix\Main\Event as D7Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;
use CApplicationException;
use CMain;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selyusize\EventsRouter\Bitrix\BitrixEvent;
use Selyusize\EventsRouter\Bitrix\BitrixEventBridge;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Exception\InvalidRoute;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Tests\Fixture\Journal;
use Selyusize\EventsRouter\Tests\Fixture\Scripted\ScriptedListener;

/**
 * События Bitrix вызываются так же, как их вызывает ядро Bitrix: см. заглушки в tests/Stub.
 *
 * @internal
 */
final class BitrixEventBridgeTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        EventManager::resetInstance();
        Journal::reset();
        ScriptedListener::reset();
        $GLOBALS['APPLICATION'] = new CMain();
        $this->directory = sys_get_temp_dir() . '/events-router-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['APPLICATION']);
        exec('rm -rf ' . escapeshellarg($this->directory));
    }

    public function testOldStyleEventReachesListenerWithFields(): void
    {
        $events = self::router(static function (RouteGroup $bitrix): void {
            $bitrix->listen('main.OnAfterUserAdd', ScriptedListener::define(static function (EventInterface $event): void {
                $bitrix = self::bitrix($event);
                Journal::write($event->getName() . ' ' . $bitrix->getModuleId() . ':' . $bitrix->getEventType() . ' ' . $bitrix->getFields()['LOGIN']);
            }));
        });
        BitrixEventBridge::attach(EventManager::getInstance(), $events);

        $arFields = ['ID' => 7, 'LOGIN' => 'ivan'];
        self::fireOldStyle('main', 'OnAfterUserAdd', $arFields);

        self::assertSame(['bitrix.main.OnAfterUserAdd main:OnAfterUserAdd ivan'], Journal::$entries);
    }

    public function testOnBeforeListenerChangesFieldsByReference(): void
    {
        $events = self::router(static function (RouteGroup $bitrix): void {
            $bitrix->listen('iblock.OnBeforeIBlockElementUpdate', ScriptedListener::define(static function (EventInterface $event): void {
                $bitrix = self::bitrix($event);
                $bitrix->setField('NAME', trim((string)$bitrix->getFields()['NAME']));
            }));
            $bitrix->listen('iblock.OnBeforeIBlockElementUpdate', ScriptedListener::define(static function (EventInterface $event): void {
                Journal::write('второй видит "' . (string)self::bitrix($event)->getFields()['NAME'] . '"');
            }));
        });
        BitrixEventBridge::attach(EventManager::getInstance(), $events);

        $arFields = ['NAME' => '  Чайник  '];
        $results = self::fireOldStyle('iblock', 'OnBeforeIBlockElementUpdate', $arFields);

        self::assertSame('Чайник', $arFields['NAME']);
        self::assertSame(['второй видит "Чайник"'], Journal::$entries);
        self::assertSame([null], $results);
    }

    public function testCancelReturnsFalseThrowsApplicationExceptionAndSkipsOthers(): void
    {
        $events = self::router(static function (RouteGroup $bitrix): void {
            $bitrix->listen('iblock.OnBeforeIBlockElementUpdate', ScriptedListener::define(static function (EventInterface $event): void {
                self::bitrix($event)->cancel('Название товара обязательно');
            }));
            $bitrix->listen('iblock.OnBeforeIBlockElementUpdate', ScriptedListener::define(static function (EventInterface $event): void {
                Journal::write('не должен вызваться');
            }));
        });
        BitrixEventBridge::attach(EventManager::getInstance(), $events);

        $arFields = ['NAME' => ''];
        $results = self::fireOldStyle('iblock', 'OnBeforeIBlockElementUpdate', $arFields);

        self::assertSame([false], $results);
        self::assertSame([], Journal::$entries);

        /** @var CMain $application */
        $application = $GLOBALS['APPLICATION'];
        $exception = $application->GetException();
        self::assertInstanceOf(CApplicationException::class, $exception);
        self::assertSame('Название товара обязательно', $exception->GetString());
    }

    public function testD7EventReachesListenerAndCancelReturnsErrorResult(): void
    {
        $events = self::router(static function (RouteGroup $bitrix): void {
            $bitrix->listen('sale.OnSaleOrderBeforeSaved', ScriptedListener::define(static function (EventInterface $event): void {
                $d7 = self::bitrix($event)->getD7Event();
                self::assertNotNull($d7);
                Journal::write('заказ ' . (string)$d7->getParameter('ENTITY'));
                self::bitrix($event)->cancel('Склад недоступен');
            }));
        });
        BitrixEventBridge::attach(EventManager::getInstance(), $events);

        $d7 = new D7Event('sale', 'OnSaleOrderBeforeSaved', ['ENTITY' => 42]);
        $d7->send();

        self::assertSame(['заказ 42'], Journal::$entries);
        self::assertCount(1, $d7->getResults());
        self::assertSame(EventResult::ERROR, $d7->getResults()[0]->getType());
        self::assertSame('Склад недоступен', $d7->getResults()[0]->getParameters());
        self::assertSame('sale', $d7->getResults()[0]->getModuleId());
    }

    public function testD7EventWithoutCancelAddsNoResult(): void
    {
        $events = self::router(static function (RouteGroup $bitrix): void {
            $bitrix->listen('sale.OnSaleOrderPaid', ScriptedListener::define(static function (EventInterface $event): void {}));
        });
        BitrixEventBridge::attach(EventManager::getInstance(), $events);

        $d7 = new D7Event('sale', 'OnSaleOrderPaid', ['ENTITY' => 42]);
        $d7->send();

        self::assertSame([], $d7->getResults());
    }

    public function testFieldsOfD7EventAreNotAvailable(): void
    {
        $bitrix = new BitrixEvent('sale', 'OnSaleOrderPaid', [new D7Event('sale', 'OnSaleOrderPaid')]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Это событие D7: используйте getD7Event()');

        $bitrix->getFields();
    }

    public function testRegistersOneHandlerPerEventAndIgnoresOtherRoutes(): void
    {
        $listener = ScriptedListener::define(static function (EventInterface $event): void {});
        $events = self::router(static function (RouteGroup $bitrix) use ($listener): void {
            $bitrix->listen('main.OnAfterUserAdd', $listener);
            $bitrix->listen('main.OnAfterUserAdd', $listener);
        });
        $events->listen('order.{order_id}.paid', $listener);
        $events->listen('#', $listener);

        BitrixEventBridge::attach(EventManager::getInstance(), $events);

        self::assertCount(1, EventManager::getInstance()->findEventHandlers('main', 'OnAfterUserAdd'));
    }

    public function testPartnerModuleIdWithDotIsEncodedInTopic(): void
    {
        self::assertSame('rasa~shop.OnOrderExport', BitrixEventBridge::topic('rasa.shop', 'OnOrderExport'));

        $events = self::router(static function (RouteGroup $bitrix): void {
            $bitrix->listen(BitrixEventBridge::topic('rasa.shop', 'OnOrderExport'), ScriptedListener::define(static function (EventInterface $event): void {
                Journal::write($event->getName() . ' ' . self::bitrix($event)->getModuleId());
            }));
        });
        BitrixEventBridge::attach(EventManager::getInstance(), $events);

        $arFields = [];
        self::fireOldStyle('rasa.shop', 'OnOrderExport', $arFields);

        self::assertSame(['bitrix.rasa~shop.OnOrderExport rasa.shop'], Journal::$entries);
    }

    public function testPrefixIncludesRouterPrefix(): void
    {
        $events = self::router(static function (RouteGroup $bitrix): void {
            $bitrix->listen('main.OnAfterUserAdd', ScriptedListener::define(static function (EventInterface $event): void {
                Journal::write($event->getName());
            }));
        });
        $events->setPrefix('shop');

        BitrixEventBridge::attach(EventManager::getInstance(), $events, 'shop.bitrix');

        $arFields = [];
        self::fireOldStyle('main', 'OnAfterUserAdd', $arFields);

        self::assertSame(['shop.bitrix.main.OnAfterUserAdd'], Journal::$entries);
    }

    #[DataProvider('provideNotExactBitrixRouteIsRejectedCases')]
    public function testNotExactBitrixRouteIsRejected(string $pattern): void
    {
        $events = self::router(static function (RouteGroup $bitrix) use ($pattern): void {
            $bitrix->listen($pattern, ScriptedListener::define(static function (EventInterface $event): void {}));
        });

        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('EventManager Bitrix подписывается только на конкретное событие');

        BitrixEventBridge::attach(EventManager::getInstance(), $events);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNotExactBitrixRouteIsRejectedCases(): iterable
    {
        yield 'звёздочка' => ['main.*'];
        yield 'решётка' => ['sale.#'];
        yield 'параметр' => ['{module}.OnAfterUserAdd'];
        yield 'лишний сегмент' => ['rasa.shop.OnOrderExport'];
        yield 'только модуль' => ['main'];
    }

    public function testLazyBuildsRouterOnceAndWritesEventsFile(): void
    {
        $file = $this->directory . '/cache/bitrix-events.php';
        $built = 0;
        $router = static function () use (&$built): EventRouter {
            ++$built;

            return self::router(static function (RouteGroup $bitrix): void {
                $bitrix->listen('main.OnAfterUserAdd', ScriptedListener::define(static function (EventInterface $event): void {
                    Journal::write($event->getName());
                }));
            });
        };

        // Первый хит: файла нет — роутер собирается, список событий записывается
        BitrixEventBridge::attachLazy(EventManager::getInstance(), $router, $file);
        self::assertSame(1, $built);
        self::assertSame(['bitrix.main.OnAfterUserAdd' => ['main', 'OnAfterUserAdd']], require $file);

        // Следующий хит: список из файла, роутер не собирается, пока не произошло событие
        EventManager::resetInstance();
        $built = 0;
        BitrixEventBridge::attachLazy(EventManager::getInstance(), $router, $file);
        self::assertSame(0, $built);

        $arFields = [];
        self::fireOldStyle('main', 'OnAfterUserAdd', $arFields);

        self::assertSame(1, $built);
        self::assertSame(['bitrix.main.OnAfterUserAdd'], Journal::$entries);
    }

    /**
     * @param callable(RouteGroup): void $bitrixRoutes
     */
    private static function router(callable $bitrixRoutes): EventRouter
    {
        $events = EventRouterFactory::create();
        $events->group('bitrix', $bitrixRoutes);

        return $events;
    }

    private static function bitrix(EventInterface $event): BitrixEvent
    {
        $bitrix = $event->getPayload();
        self::assertInstanceOf(BitrixEvent::class, $bitrix);

        return $bitrix;
    }

    /**
     * Как Bitrix вызывает старые события: GetModuleEvents() + ExecuteModuleEventEx() с &$arFields.
     *
     * @param array<array-key, mixed> $arFields
     *
     * @return list<mixed> что вернул каждый обработчик
     */
    private static function fireOldStyle(string $moduleId, string $eventType, array &$arFields): array
    {
        $results = [];

        foreach (GetModuleEvents($moduleId, $eventType, true) as $arEvent) {
            $results[] = ExecuteModuleEventEx($arEvent, [&$arFields]);
        }

        return $results;
    }
}
