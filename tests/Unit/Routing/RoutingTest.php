<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Routing;

use Fixture\Listener;
use Fixture\Middleware;
use PHPUnit\Framework\TestCase;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Routing\Route;
use Selyusize\EventsRouter\Routing\RouteGroup;

/**
 * Классы из неймспейса Fixture не существуют: роутер не загружает классы
 * слушателей и middleware при регистрации, ему достаточно имени.
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
        $events = new EventRouter();

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
        $events = new EventRouter();

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
        $events = new EventRouter();

        $group = $events->group('order', static function (RouteGroup $group): void {
            $group->listen('created', Listener\Any::class);
        });

        self::assertSame([], self::dump($events)[0][2]);

        $group->add(Middleware\Late::class);

        self::assertSame(['Late'], self::dump($events)[0][2]);
    }

    public function testRoutesAreKeptInDeclarationOrderWithIndex(): void
    {
        $events = new EventRouter();
        $events->listen('b', Listener\Second::class);
        $events->group('a', static function (RouteGroup $group): void {
            $group->listen('x', Listener\Third::class);
        });
        $events->listen('a.x', Listener\Fourth::class);

        self::assertSame([0, 1, 2], array_map(static fn (Route $route): int => $route->getIndex(), $events->getRoutes()));
        self::assertSame(['b', 'a.x', 'a.x'], array_map(static fn (Route $route): string => $route->getPattern()->getPattern(), $events->getRoutes()));
    }

    public function testPrefixCanBeSetAfterRoutesAndChanged(): void
    {
        $events = new EventRouter();
        $events->group('order', static function (RouteGroup $group): void {
            $group->listen('created', Listener\Any::class);
        });

        $events->setPrefix('shop');
        self::assertSame('shop', $events->getPrefix());
        self::assertSame('shop.order.created', $events->getRoutes()[0]->getPattern()->getPattern());

        $events->setPrefix('');
        self::assertSame('order.created', $events->getRoutes()[0]->getPattern()->getPattern());
    }

    public function testInvalidPrefixLeavesRouterUnchanged(): void
    {
        $events = new EventRouter();
        $events->setPrefix('shop');
        $events->listen('{id}', Listener\Any::class);

        try {
            $events->setPrefix('{id}');
            self::fail('Ожидалось исключение');
        } catch (InvalidTopicPattern $error) {
            self::assertStringContainsString('параметр {id} встречается дважды', $error->getMessage());
        }

        self::assertSame('shop', $events->getPrefix());
        self::assertSame('shop.{id}', $events->getRoutes()[0]->getPattern()->getPattern());
    }

    public function testEmptyPatternInsideGroupListensToGroupPrefix(): void
    {
        $events = new EventRouter();
        $events->group('order', static function (RouteGroup $group): void {
            $group->listen('', Listener\Any::class);
        });

        self::assertSame('order', $events->getRoutes()[0]->getPattern()->getPattern());
    }

    public function testEmptyPatternWithoutAnyPrefixIsRejected(): void
    {
        $this->expectException(InvalidTopicPattern::class);
        $this->expectExceptionMessage('шаблон пустой');

        (new EventRouter())->group('', static function (RouteGroup $group): void {
            $group->listen('', Listener\Any::class);
        });
    }

    public function testInvalidPatternIsRejectedAtRegistration(): void
    {
        $this->expectException(InvalidTopicPattern::class);
        $this->expectExceptionMessage('"order.{orderId}.paid"');

        (new EventRouter())->group('order', static function (RouteGroup $group): void {
            $group->listen('{orderId}.paid', Listener\Any::class);
        });
    }

    public function testDuplicateParameterAcrossGroupAndRouteIsRejected(): void
    {
        $this->expectException(InvalidTopicPattern::class);
        $this->expectExceptionMessage('параметр {id} встречается дважды');

        (new EventRouter())->group('user.{id}', static function (RouteGroup $group): void {
            $group->listen('order.{id}', Listener\Any::class);
        });
    }

    public function testRouteNameAndInstances(): void
    {
        $listener = new class implements ListenerInterface {
            public function handle(EventInterface $event): void {}
        };
        $middleware = new class implements MiddlewareInterface {
            public function process(EventInterface $event, ListenerInterface $next): void {}
        };

        $events = new EventRouter();
        $route = $events->listen('order.paid', $listener)
            ->add($middleware)
            ->name('order.mark_paid');

        self::assertSame($listener, $route->getListener());
        self::assertSame([$middleware], $route->getMiddleware());
        self::assertSame('order.mark_paid', $route->getName());
        self::assertNull($events->listen('order.created', Listener\Any::class)->getName());
    }

    public function testGroupExposesPrefixAndOwnMiddleware(): void
    {
        $inner = null;

        (new EventRouter())->group('order', static function (RouteGroup $group) use (&$inner): void {
            $inner = $group->group('{order_id}', static function (): void {})
                ->add(Middleware\First::class)
                ->add(Middleware\Second::class);
        })->add(Middleware\Outer::class);

        self::assertInstanceOf(RouteGroup::class, $inner);
        self::assertSame('{order_id}', $inner->getPrefix());
        self::assertSame([Middleware\Second::class, Middleware\First::class], $inner->getMiddleware());
        self::assertSame(['order', '{order_id}'], array_map(static fn (RouteGroup $group): string => $group->getPrefix(), $inner->getGroups()));
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    private static function dump(EventRouter $events): array
    {
        $short = static fn (object|string $class, string $namespace): string => str_replace($namespace, '', \is_string($class) ? $class : $class::class);

        return array_map(static fn (Route $route): array => [
            $route->getPattern()->getPattern(),
            $short($route->getListener(), 'Fixture\Listener\\'),
            array_map(static fn (object|string $middleware): string => $short($middleware, 'Fixture\Middleware\\'), $route->getMiddleware()),
        ], $events->getRoutes());
    }
}
