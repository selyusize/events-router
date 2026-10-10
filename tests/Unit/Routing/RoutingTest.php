<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Routing;

use Closure;
use Fixture\Listener;
use Fixture\Middleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Exception\InvalidRoute;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Routing\CompiledRoute;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Tests\Fixture\NotAHandler;

require_once __DIR__ . '/../../Fixture/routing-classes.php';

/**
 * Классы из неймспейса Fixture — пустые заглушки из routing-classes.php.
 *
 * @internal
 */
final class RoutingTest extends TestCase
{
    /**
     * Файл маршрутов в стиле Slim из плана: разделы, пустые группы, вложенность,
     * один префикс в двух группах с разными middleware.
     */
    public function testBuildsRoutesFileInSlimStyle(): void
    {
        $events = EventRouterFactory::create();

        (static function (EventRouter $events): void {
            $events->setPrefix('shop');

            // =================================== Пользователи ===================================

            $events->group('user', static function (RouteGroup $group): void {
                $group->listen('registered', Listener\User\CreateBonusAccount::class);
                $group->listen('registered', Listener\User\SendWelcomeEmail::class);
                $group->listen('{user_id}.deactivated', Listener\User\RevokeTokens::class);
            })
                ->add(Middleware\EventLog\EventLogger::class);

            // =================================== Заказы ===================================

            $events->group('', static function (RouteGroup $group): void {
                $group->group('order', static function (RouteGroup $order): void {
                    $order->listen('created', Listener\Order\ReserveStock::class);
                    $order->listen('created', Listener\Order\SendConfirmationEmail::class);

                    $order->group('{order_id}', static function (RouteGroup $one): void {
                        $one->listen('paid', Listener\Order\MarkOrderPaid::class);
                        $one->listen('paid', Listener\Bonuses\AccrueBonuses::class);
                        $one->listen('payment.#', Listener\Order\AuditPayment::class);
                    })
                        ->add(Middleware\Idempotency\IdempotencyGuard::class);

                    $order->listen('*.cancelled', Listener\Order\RefundPayment::class);
                })
                    ->add(Middleware\DataEnrichment\SetUserData::class);

                // тот же префикс 'order', но другой набор middleware
                $group->group('order', static function (RouteGroup $order): void {
                    $order->listen('{order_id}.unpaid_reminder', Listener\Order\SendUnpaidReminder::class);
                });
            })
                ->add(Middleware\EventLog\EventLogger::class);
        })($events);

        self::assertSame([
            ['shop.user.registered', 'User\CreateBonusAccount', ['EventLog\EventLogger']],
            ['shop.user.registered', 'User\SendWelcomeEmail', ['EventLog\EventLogger']],
            ['shop.user.{user_id}.deactivated', 'User\RevokeTokens', ['EventLog\EventLogger']],
            ['shop.order.created', 'Order\ReserveStock', ['EventLog\EventLogger', 'DataEnrichment\SetUserData']],
            ['shop.order.created', 'Order\SendConfirmationEmail', ['EventLog\EventLogger', 'DataEnrichment\SetUserData']],
            ['shop.order.{order_id}.paid', 'Order\MarkOrderPaid', ['EventLog\EventLogger', 'DataEnrichment\SetUserData', 'Idempotency\IdempotencyGuard']],
            ['shop.order.{order_id}.paid', 'Bonuses\AccrueBonuses', ['EventLog\EventLogger', 'DataEnrichment\SetUserData', 'Idempotency\IdempotencyGuard']],
            ['shop.order.{order_id}.payment.#', 'Order\AuditPayment', ['EventLog\EventLogger', 'DataEnrichment\SetUserData', 'Idempotency\IdempotencyGuard']],
            ['shop.order.*.cancelled', 'Order\RefundPayment', ['EventLog\EventLogger', 'DataEnrichment\SetUserData']],
            ['shop.order.{order_id}.unpaid_reminder', 'Order\SendUnpaidReminder', ['EventLog\EventLogger']],
        ], self::dump($events));
    }

    public function testMiddlewareOrderMatchesSlim(): void
    {
        $events = EventRouterFactory::create();

        $events->group('outer', static function (RouteGroup $outer): void {
            $outer->group('inner', static function (RouteGroup $inner): void {
                $inner->listen('event', Listener\Any::class)
                    ->add(Middleware\RouteFirst::class)
                    ->add(Middleware\RouteSecond::class);
            })
                ->add(Middleware\InnerFirst::class)
                ->add(Middleware\InnerSecond::class);
        })
            ->add(Middleware\OuterFirst::class)
            ->add(Middleware\OuterSecond::class);

        // внешняя группа → вложенная → маршрут; внутри уровня последний добавленный — первый
        self::assertSame(
            ['OuterSecond', 'OuterFirst', 'InnerSecond', 'InnerFirst', 'RouteSecond', 'RouteFirst'],
            self::dump($events)[0][2],
        );
    }

    public function testGroupMiddlewareAddedAfterRoutesAppliesToThem(): void
    {
        $events = EventRouterFactory::create();

        $group = $events->group('order', static function (RouteGroup $group): void {
            $group->listen('created', Listener\Any::class);
        });

        self::assertSame([], self::dump($events)[0][2]);

        $group->add(Middleware\Late::class);

        self::assertSame(['Late'], self::dump($events)[0][2]);
    }

    public function testRoutesAreKeptInDeclarationOrder(): void
    {
        $events = EventRouterFactory::create();
        $events->listen('b', Listener\Second::class);
        $events->group('a', static function (RouteGroup $group): void {
            $group->listen('x', Listener\Third::class);
        });
        $events->listen('a.x', Listener\Fourth::class);

        self::assertSame(['b', 'a.x', 'a.x'], array_map(static fn (CompiledRoute $route): string => $route->getPattern()->getPattern(), $events->getRoutes()));
    }

    public function testPrefixCanBeSetAfterRoutesAndChanged(): void
    {
        $events = EventRouterFactory::create();
        $events->group('order', static function (RouteGroup $group): void {
            $group->listen('created', Listener\Any::class);
        });

        $events->setPrefix('shop');
        self::assertSame('shop.order.created', $events->getRoutes()[0]->getPattern()->getPattern());

        $events->setPrefix('');
        self::assertSame('order.created', $events->getRoutes()[0]->getPattern()->getPattern());
    }

    public function testInvalidPrefixLeavesRouterUnchanged(): void
    {
        $events = EventRouterFactory::create();
        $events->setPrefix('shop');
        $events->listen('{id}', Listener\Any::class);

        try {
            $events->setPrefix('{id}');
            self::fail('Ожидалось исключение');
        } catch (InvalidTopicPattern $error) {
            self::assertStringContainsString('параметр {id} встречается дважды', $error->getMessage());
        }

        self::assertSame('shop.{id}', $events->getRoutes()[0]->getPattern()->getPattern());
    }

    public function testEmptyPatternInsideGroupListensToGroupPrefix(): void
    {
        $events = EventRouterFactory::create();
        $events->group('order', static function (RouteGroup $group): void {
            $group->listen('', Listener\Any::class);
        });

        self::assertSame('order', $events->getRoutes()[0]->getPattern()->getPattern());
    }

    public function testEmptyPatternWithoutAnyPrefixIsRejected(): void
    {
        $this->expectException(InvalidTopicPattern::class);
        $this->expectExceptionMessage('шаблон пустой');

        EventRouterFactory::create()->group('', static function (RouteGroup $group): void {
            $group->listen('', Listener\Any::class);
        });
    }

    public function testInvalidPatternIsRejectedAtRegistration(): void
    {
        $this->expectException(InvalidTopicPattern::class);
        $this->expectExceptionMessage('"order.{orderId}.paid"');

        EventRouterFactory::create()->group('order', static function (RouteGroup $group): void {
            $group->listen('{orderId}.paid', Listener\Any::class);
        });
    }

    public function testDuplicateParameterAcrossGroupAndRouteIsRejected(): void
    {
        $this->expectException(InvalidTopicPattern::class);
        $this->expectExceptionMessage('параметр {id} встречается дважды');

        EventRouterFactory::create()->group('user.{id}', static function (RouteGroup $group): void {
            $group->listen('order.{id}', Listener\Any::class);
        });
    }

    /**
     * @param Closure(EventRouter): mixed $declare
     */
    #[DataProvider('provideInvalidClassIsRejectedAtDeclarationCases')]
    public function testInvalidClassIsRejectedAtDeclaration(Closure $declare, string $message): void
    {
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage($message);

        $declare(EventRouterFactory::create());
    }

    /**
     * @return iterable<string, array{Closure(EventRouter): mixed, string}>
     */
    public static function provideInvalidClassIsRejectedAtDeclarationCases(): iterable
    {
        /** @var class-string<ListenerInterface> $missingListener */
        $missingListener = 'Fixture\Listener\Missing';

        /** @var class-string<MiddlewareInterface> $missingMiddleware */
        $missingMiddleware = 'Fixture\Middleware\Missing';

        /** @var class-string<ListenerInterface> $notListener */
        $notListener = NotAHandler::class;

        /** @var class-string<MiddlewareInterface> $notMiddleware */
        $notMiddleware = NotAHandler::class;

        yield 'слушатель не найден' => [
            static fn (EventRouter $events): mixed => $events->listen('order.paid', $missingListener),
            'класс слушателя "Fixture\Listener\Missing" не найден',
        ];
        yield 'слушатель без интерфейса' => [
            static fn (EventRouter $events): mixed => $events->listen('order.paid', $notListener),
            'должен реализовать "' . ListenerInterface::class . '"',
        ];
        yield 'middleware маршрута не найден' => [
            static fn (EventRouter $events): mixed => $events->listen('order.paid', Listener\Any::class)->add($missingMiddleware),
            'класс middleware "Fixture\Middleware\Missing" не найден',
        ];
        yield 'middleware группы без интерфейса' => [
            static fn (EventRouter $events): mixed => $events->group('order', static function (): void {})->add($notMiddleware),
            'должен реализовать "' . MiddlewareInterface::class . '"',
        ];
        yield 'middleware роутера не найден' => [
            static fn (EventRouter $events): mixed => $events->add($missingMiddleware),
            'класс middleware "Fixture\Middleware\Missing" не найден',
        ];
    }

    public function testRouteAndCompiledRoute(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(EventInterface $event, Closure $next): void {}
        };

        $events = EventRouterFactory::create();
        $definition = $events->listen('order.paid', Listener\MarkOrderPaid::class)
            ->add($middleware);

        self::assertSame('order.paid', $definition->getPattern());
        self::assertSame(Listener\MarkOrderPaid::class, $definition->getListener());
        self::assertSame([$middleware], $definition->getMiddleware());

        $route = $events->getRoutes()[0];
        self::assertSame(Listener\MarkOrderPaid::class, $route->getListener());
        self::assertSame([$middleware], $route->getMiddleware());
    }

    public function testRouteTableIsRebuiltOnlyAfterChanges(): void
    {
        $events = EventRouterFactory::create();
        $definition = $events->listen('order.paid', Listener\Any::class);

        $first = $events->getRoutes();
        self::assertSame($first, $events->getRoutes(), 'без изменений таблица та же');

        $definition->add(Middleware\Late::class);
        self::assertNotSame($first[0], $events->getRoutes()[0]);
        self::assertSame([Middleware\Late::class], $events->getRoutes()[0]->getMiddleware());
    }

    public function testGroupExposesPrefixMiddlewareAndChildren(): void
    {
        $inner = null;
        $definition = null;

        EventRouterFactory::create()->group('order', static function (RouteGroup $group) use (&$inner, &$definition): void {
            $inner = $group->group('{order_id}', static function (RouteGroup $one) use (&$definition): void {
                $definition = $one->listen('paid', Listener\Any::class);
            })
                ->add(Middleware\First::class)
                ->add(Middleware\Second::class);
        })->add(Middleware\Outer::class);

        self::assertInstanceOf(RouteGroup::class, $inner);
        self::assertSame('{order_id}', $inner->getPrefix());
        self::assertSame([Middleware\Second::class, Middleware\First::class], $inner->getMiddleware());
        self::assertSame([$definition], $inner->getChildren());
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    private static function dump(EventRouter $events): array
    {
        $short = static fn (object|string $class, string $namespace): string => str_replace($namespace, '', \is_string($class) ? $class : $class::class);

        return array_map(static fn (CompiledRoute $route): array => [
            $route->getPattern()->getPattern(),
            $short($route->getListener(), 'Fixture\Listener\\'),
            array_map(static fn (object|string $middleware): string => $short($middleware, 'Fixture\Middleware\\'), $route->getMiddleware()),
        ], $events->getRoutes());
    }
}
