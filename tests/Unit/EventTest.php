<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selyusize\EventsRouter\Documentation;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\Exception\ExceptionInterface;
use Selyusize\EventsRouter\Exception\InvalidEventName;
use stdClass;

/**
 * @internal
 */
final class EventTest extends TestCase
{
    #[DataProvider('provideAcceptsValidNameCases')]
    public function testAcceptsValidName(string $name): void
    {
        self::assertSame($name, (new Event($name))->getName());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAcceptsValidNameCases(): iterable
    {
        yield 'один сегмент' => ['ping'];
        yield 'несколько сегментов' => ['shop.order.42.paid'];
        yield 'регистр сохраняется' => ['bitrix.main.OnAfterUserAdd'];
        yield 'дефис и подчёркивание' => ['order.unpaid-reminder.sent_at'];
        yield 'закодированная точка в id модуля' => ['bitrix.rasa~shop.OnSomething'];
        yield 'кириллица' => ['заказ.оплачен'];
    }

    #[DataProvider('provideRejectsInvalidNameCases')]
    public function testRejectsInvalidName(string $name, string $reason): void
    {
        $this->expectException(InvalidEventName::class);
        $this->expectExceptionMessage($reason);

        new Event($name);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRejectsInvalidNameCases(): iterable
    {
        yield 'пустое' => ['', 'имя пустое'];
        yield 'пробел' => ['order paid', 'пробельные символы'];
        yield 'перевод строки' => ["order.\npaid", 'пробельные символы'];
        yield 'звёздочка' => ['order.*', 'только в шаблонах'];
        yield 'решётка' => ['order.#', 'только в шаблонах'];
        yield 'параметр' => ['order.{order_id}.paid', 'только в шаблонах'];
        yield 'точка в начале' => ['.order', 'пустой сегмент'];
        yield 'точка в конце' => ['order.', 'пустой сегмент'];
        yield 'две точки подряд' => ['order..paid', 'пустой сегмент'];
    }

    public function testInvalidNameExceptionLinksToDocumentationAndIsCatchableAsLibraryException(): void
    {
        try {
            new Event('order..paid');
            self::fail('Ожидалось исключение');
        } catch (ExceptionInterface $error) {
            self::assertInstanceOf(InvalidEventName::class, $error);
            self::assertStringContainsString(Documentation::errorUrl('invalid-event-name'), $error->getMessage());
        }
    }

    public function testKeepsPayloadAsIs(): void
    {
        $payload = new stdClass();

        self::assertNull((new Event('ping'))->getPayload());
        self::assertSame(['amount' => 1500], (new Event('order.paid', ['amount' => 1500]))->getPayload());
        self::assertSame($payload, (new Event('order.paid', $payload))->getPayload());
    }

    public function testReturnsAttributesAndDefault(): void
    {
        $event = new Event('order.paid', attributes: ['order_id' => '42', 'empty' => null]);

        self::assertSame(['order_id' => '42', 'empty' => null], $event->getAttributes());
        self::assertSame('42', $event->getAttribute('order_id'));
        self::assertSame('нет', $event->getAttribute('missing', 'нет'));
        self::assertNull($event->getAttribute('empty', 'нет'), 'существующий атрибут со значением null не заменяется на default');
    }

    public function testWithAttributeReturnsCopyAndKeepsOriginalUnchanged(): void
    {
        $payload = new stdClass();
        $original = new Event('order.paid', $payload, ['source' => 'api']);

        $copy = $original->withAttribute('order_id', '42')->withAttribute('source', 'queue');

        self::assertNotSame($original, $copy);
        self::assertSame(['source' => 'api'], $original->getAttributes());
        self::assertSame(['source' => 'queue', 'order_id' => '42'], $copy->getAttributes());
        self::assertSame('order.paid', $copy->getName());
        self::assertSame($payload, $copy->getPayload(), 'payload не копируется');
    }
}
