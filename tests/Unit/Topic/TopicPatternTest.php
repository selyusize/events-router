<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Topic;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selyusize\EventsRouter\Contract\Exception\ExceptionInterface;
use Selyusize\EventsRouter\Documentation;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Topic\TopicPattern;

/**
 * @internal
 */
final class TopicPatternTest extends TestCase
{
    /**
     * @param array<string, string>|null $expected
     */
    #[DataProvider('provideMatchCases')]
    public function testMatch(string $pattern, string $topic, ?array $expected): void
    {
        $compiled = TopicPattern::fromString($pattern);

        self::assertSame($expected, $compiled->match($topic));
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>|null}>
     */
    public static function provideMatchCases(): iterable
    {
        // точное совпадение
        yield 'литерал совпадает' => ['order.paid', 'order.paid', []];
        yield 'литерал: другой сегмент' => ['order.paid', 'order.created', null];
        yield 'литерал: топик длиннее' => ['order.paid', 'order.paid.late', null];
        yield 'литерал: топик короче' => ['order.paid', 'order', null];
        yield 'литерал: регистр учитывается' => ['bitrix.main.OnAfterUserAdd', 'bitrix.main.onafteruseradd', null];
        yield 'пустой топик' => ['order', '', null];

        // параметры
        yield 'параметр' => ['order.{order_id}.paid', 'order.42.paid', ['order_id' => '42']];
        yield 'параметр не пустой' => ['order.{order_id}.paid', 'order..paid', null];
        yield 'два параметра' => ['user.{user_id}.order.{order_id}', 'user.7.order.42', ['user_id' => '7', 'order_id' => '42']];
        yield 'regex подходит' => ['order.{order_id:\d+}.paid', 'order.42.paid', ['order_id' => '42']];
        yield 'regex не подходит' => ['order.{order_id:\d+}.paid', 'order.abc.paid', null];
        yield 'regex целиком, не подстрока' => ['order.{order_id:\d+}', 'order.42abc', null];
        yield 'regex с квантификатором в скобках' => ['order.{code:\d{3}}', 'order.123', ['code' => '123']];
        yield 'regex с квантификатором: не та длина' => ['order.{code:\d{3}}', 'order.1234', null];
        yield 'regex с альтернативой' => ['order.{status:paid|cancelled}', 'order.cancelled', ['status' => 'cancelled']];
        yield 'regex: альтернатива целиком' => ['order.{status:paid|cancelled}', 'order.unpaid', null];
        yield 'regex с unicode' => ['заказ.{status:\p{L}+}', 'заказ.оплачен', ['status' => 'оплачен']];
        yield 'regex с unicode: цифры не буквы' => ['заказ.{status:\p{L}+}', 'заказ.42', null];
        yield 'кириллица в литерале' => ['заказ.{id}', 'заказ.оплачен', ['id' => 'оплачен']];

        // *
        yield '* — один сегмент' => ['order.*.cancelled', 'order.42.cancelled', []];
        yield '* — не ноль сегментов' => ['order.*.cancelled', 'order.cancelled', null];
        yield '* — не два сегмента' => ['order.*.cancelled', 'order.42.items.cancelled', null];

        // #
        yield '# — ноль сегментов' => ['order.#', 'order', []];
        yield '# — один сегмент' => ['order.#', 'order.created', []];
        yield '# — много сегментов' => ['order.#', 'order.42.payment.failed', []];
        yield '# — другой префикс' => ['order.#', 'user.created', null];
        yield '# — один на всё' => ['#', 'any.topic.at.all', []];
        yield '# в середине' => ['order.#.failed', 'order.42.payment.failed', []];
        yield '# в середине, ноль сегментов' => ['order.#.failed', 'order.failed', []];
        yield '# в середине, не тот конец' => ['order.#.failed', 'order.42.paid', null];
        yield '# и параметр после него' => ['order.#.{last}', 'order.42.payment.failed', ['last' => 'failed']];
        yield '# и параметр до него' => ['order.{order_id}.payment.#', 'order.42.payment.failed.twice', ['order_id' => '42']];
        yield '# с откатом под regex' => ['a.#.{n:\d+}.end', 'a.x.1.y.2.end', ['n' => '2']];
        yield 'два #' => ['#.payment.#', 'order.42.payment.failed', []];
    }

    #[DataProvider('provideRejectsInvalidPatternCases')]
    public function testRejectsInvalidPattern(string $pattern, string $reason): void
    {
        $this->expectException(InvalidTopicPattern::class);
        $this->expectExceptionMessage($reason);

        TopicPattern::fromString($pattern);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRejectsInvalidPatternCases(): iterable
    {
        yield 'пустой' => ['', 'шаблон пустой'];
        yield 'точка в начале' => ['.order', 'пустой сегмент'];
        yield 'точка в конце' => ['order.', 'пустой сегмент'];
        yield 'две точки' => ['order..paid', 'пустой сегмент'];
        yield 'пробел' => ['order paid', 'пробельные символы'];
        yield 'незакрытая скобка' => ['order.{order_id', 'не закрыта фигурная скобка'];
        yield 'лишняя скобка' => ['order.order_id}', 'лишняя закрывающая'];
        yield 'параметр в части сегмента' => ['order-{order_id}', 'должен занимать весь сегмент'];
        yield 'параметр с хвостом' => ['order.{order_id}x', 'должен занимать весь сегмент'];
        yield '* в части сегмента' => ['ord*.paid', 'должны занимать весь сегмент'];
        yield '# в части сегмента' => ['order.pay#', 'должны занимать весь сегмент'];
        yield 'пустое имя параметра' => ['order.{}', 'имя параметра'];
        yield 'имя параметра с цифры' => ['order.{1id}', 'имя параметра'];
        yield 'имя параметра в camelCase' => ['order.{orderId}', 'snake_case'];
        yield 'имя параметра с заглавной' => ['order.{Id}', 'snake_case'];
        yield 'имя параметра с _ в начале' => ['order.{_id}', 'snake_case'];
        yield 'имя параметра с дефисом' => ['order.{order-id}', 'имя параметра'];
        yield 'пустое ограничение' => ['order.{id:}', 'пустое ограничение'];
        yield 'сломанный regex' => ['order.{id:[0-9}', 'ошибка в regex параметра {id}'];
        yield 'кириллица в имени параметра' => ['order.{статус}', 'имя параметра'];
        yield 'некомпилируемый regex' => ['order.{id:(\d+}', 'ошибка в regex параметра {id}'];
        yield 'повтор параметра' => ['user.{id}.order.{id}', 'параметр {id} встречается дважды'];
    }

    public function testInvalidPatternExceptionLinksToDocumentationAndIsCatchableAsLibraryException(): void
    {
        try {
            TopicPattern::fromString('order..paid');
            self::fail('Ожидалось исключение');
        } catch (ExceptionInterface $error) {
            self::assertInstanceOf(InvalidTopicPattern::class, $error);
            self::assertStringContainsString(Documentation::errorUrl('invalid-topic-pattern'), $error->getMessage());
        }
    }

    public function testExposesPattern(): void
    {
        $pattern = TopicPattern::fromString('user.{user_id}.order.{order_id:\d+}.#');

        self::assertSame('user.{user_id}.order.{order_id:\d+}.#', $pattern->getPattern());
    }
}
