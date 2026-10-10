<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Routing;

use Closure;
use PHPUnit\Framework\TestCase;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Exception\InvalidRoute;
use Selyusize\EventsRouter\Routing\CompiledRoute;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Tests\Fixture\Journal;
use Selyusize\EventsRouter\Tests\Fixture\MarkingMiddleware;
use Selyusize\EventsRouter\Tests\Fixture\RecordingListener;
use Selyusize\EventsRouter\Tests\Fixture\RecordingMiddleware;

/**
 * @internal
 */
final class RouteCacheTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        Journal::reset();
        $this->file = sys_get_temp_dir() . '/events-router-test-' . bin2hex(random_bytes(4)) . '/cache/routes.php';
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg(\dirname($this->file, 2)));
    }

    public function testFirstLoadWritesCacheSecondSkipsRoutesFile(): void
    {
        $calls = 0;
        $routes = static function (EventRouter $events) use (&$calls): void {
            ++$calls;
            $events->setPrefix('shop');
            $events->add(RecordingMiddleware::class);
            $events->group('order', static function (RouteGroup $order): void {
                $order->listen('{order_id:\d+}.paid', RecordingListener::class);
            })->add(MarkingMiddleware::class);
        };

        $first = $this->router()->loadRoutes($routes);
        self::assertFileExists($this->file);

        $second = $this->router()->loadRoutes($routes);

        self::assertSame(1, $calls, 'при чтении из кэша файл маршрутов не выполняется');
        self::assertSame(self::dump($first->getRoutes()), self::dump($second->getRoutes()));
        self::assertSame(['order_id' => '42'], $second->match('shop.order.42.paid')[0]->getParameters());
        self::assertSame([], $second->match('shop.order.abc.paid'), 'ограничение \d+ работает и из кэша');

        $second->dispatch(new Event('shop.order.42.paid'));

        self::assertSame(['RecordingMiddleware', 'MarkingMiddleware mark', 'RecordingListener shop.order.42.paid'], Journal::$entries);
    }

    public function testRouterMiddlewareAddedOutsideRoutesFileIsNotCached(): void
    {
        $routes = static function (EventRouter $events): void {
            $events->listen('order.paid', RecordingListener::class);
        };

        $this->router()->add(MarkingMiddleware::class)->loadRoutes($routes);
        $cached = $this->router()->add(MarkingMiddleware::class)->loadRoutes($routes);

        $cached->dispatch(new Event('order.paid'));

        self::assertSame(['MarkingMiddleware mark', 'RecordingListener order.paid'], Journal::$entries);
    }

    public function testWithoutCacheLoadRoutesJustCallsRoutes(): void
    {
        $events = EventRouterFactory::create()->loadRoutes(static function (EventRouter $events): void {
            $events->listen('order.paid', RecordingListener::class);
        });
        $events->listen('order.created', RecordingListener::class);

        self::assertCount(2, $events->getRoutes());
    }

    public function testRoutesOutsideLoadRoutesAreRejectedWithCache(): void
    {
        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('маршруты объявляются только внутри loadRoutes()');

        $this->router()->listen('order.paid', RecordingListener::class);
    }

    public function testLoadRoutesTwiceIsRejectedWithCache(): void
    {
        $events = $this->router()->loadRoutes(static function (EventRouter $events): void {});

        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('loadRoutes() вызывается один раз');

        $events->loadRoutes(static function (EventRouter $events): void {});
    }

    public function testMiddlewareObjectCannotBeCached(): void
    {
        $object = new class implements MiddlewareInterface {
            public function process(EventInterface $event, Closure $next): void
            {
                $next($event);
            }
        };

        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('кэш маршрутов хранит middleware только именем класса');

        $this->router()->loadRoutes(static function (EventRouter $events) use ($object): void {
            $events->listen('order.paid', RecordingListener::class)->add($object);
        });
    }

    public function testBrokenOrOutdatedCacheIsRebuilt(): void
    {
        $routes = static function (EventRouter $events): void {
            $events->listen('order.paid', RecordingListener::class);
        };

        mkdir(\dirname($this->file), 0o775, true);

        foreach (["<?php return ['format' => 0];", '<?php syntax error'] as $content) {
            file_put_contents($this->file, $content);

            self::assertCount(1, $this->router()->loadRoutes($routes)->getRoutes());
            self::assertStringContainsString("'format' => 2", (string)file_get_contents($this->file));
        }
    }

    private function router(): EventRouter
    {
        return EventRouterFactory::create(config: ['route_cache_file' => $this->file]);
    }

    /**
     * @param list<CompiledRoute> $routes
     *
     * @return list<array{string, string, list<mixed>}>
     */
    private static function dump(array $routes): array
    {
        return array_map(static fn (CompiledRoute $route): array => [$route->getPattern()->getPattern(), $route->getListener(), $route->getMiddleware()], $routes);
    }
}
