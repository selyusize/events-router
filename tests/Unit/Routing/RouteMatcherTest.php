<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Routing;

use Fixture\Listener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Routing\RouteMatch;

require_once __DIR__ . '/../../Fixture/routing-classes.php';

/**
 * @internal
 */
final class RouteMatcherTest extends TestCase
{
    /**
     * Файл маршрутов из документации: examples/routes/events.php.
     *
     * @param list<array{string, array<string, string>}> $expected
     */
    #[DataProvider('provideDocumentedRoutesFileCases')]
    public function testDocumentedRoutesFile(string $topic, array $expected): void
    {
        $events = EventRouterFactory::create();
        (require __DIR__ . '/../../../examples/routes/events.php')($events);

        self::assertSame($expected, self::dump($events->match($topic), 'App\Events\Listener\\'));
    }

    /**
     * @return iterable<string, array{string, list<array{string, array<string, string>}>}>
     */
    public static function provideDocumentedRoutesFileCases(): iterable
    {
        yield 'два слушателя в порядке строк' => ['shop.order.created', [
            ['Order\ReserveStock', []],
            ['Order\SendConfirmationEmail', []],
        ]];

        yield 'параметр из префикса вложенной группы' => ['shop.order.42.paid', [
            ['Order\MarkOrderPaid', ['order_id' => '42']],
            ['Bonuses\AccrueBonuses', ['order_id' => '42']],
        ]];

        yield '# после параметра' => ['shop.order.42.payment.failed', [
            ['Order\AuditPayment', ['order_id' => '42']],
        ]];

        yield '*' => ['shop.order.42.cancelled', [
            ['Order\RefundPayment', []],
        ]];

        yield 'тот же префикс в другой группе' => ['shop.order.42.unpaid_reminder', [
            ['Order\SendUnpaidReminder', ['order_id' => '42']],
        ]];

        yield 'параметр в другом месте' => ['shop.user.7.deactivated', [
            ['User\RevokeTokens', ['user_id' => '7']],
        ]];

        yield 'не хватает сегмента' => ['shop.order.paid', []];
        yield 'без префикса роутера' => ['order.created', []];
        yield 'неизвестное событие' => ['shop.catalog.updated', []];
    }

    public function testMatchesFromDifferentGroupsComeInDeclarationOrderWithOwnParameters(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.#', Listener\First::class);
        $events->group('order', static function (RouteGroup $group): void {
            $group->listen('{id}.paid', Listener\Second::class);
            $group->listen('*.paid', Listener\Third::class);
        });
        $events->listen('order.{order_id}.{status}', Listener\Fourth::class);
        $events->listen('order.{order_id}.cancelled', Listener\NotMatching::class);

        self::assertSame([
            ['First', []],
            ['Second', ['id' => '42']],
            ['Third', []],
            ['Fourth', ['order_id' => '42', 'status' => 'paid']],
        ], self::dump($events->match('order.42.paid'), 'Fixture\Listener\\'));
    }

    public function testSeesRoutesAddedAndPrefixChangedAfterFirstMatch(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.created', Listener\First::class);

        self::assertCount(1, $events->match('order.created'));

        $events->listen('order.created', Listener\Second::class);
        self::assertCount(2, $events->match('order.created'));

        $events->setPrefix('shop');
        self::assertSame([], $events->match('order.created'));
        self::assertCount(2, $events->match('shop.order.created'));
    }

    public function testReturnsRoutesFromRouteTable(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('order.created', Listener\First::class);

        self::assertSame($events->getRoutes()[0], $events->match('order.created')[0]->getRoute());
    }

    /**
     * @param list<RouteMatch> $matches
     *
     * @return list<array{string, array<string, string>}>
     */
    private static function dump(array $matches, string $namespace): array
    {
        return array_map(static fn (RouteMatch $match): array => [
            str_replace($namespace, '', $match->getRoute()->getListener()),
            $match->getParameters(),
        ], $matches);
    }
}
