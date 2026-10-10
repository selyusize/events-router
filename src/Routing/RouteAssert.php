<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Override;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Exception\InvalidRoute;
use Webmozart\Assert\Assert;

/**
 * Проверки объявления маршрутов: Assert из webmozart/assert, но вместо его исключения — InvalidRoute.
 *
 * @internal
 */
final class RouteAssert extends Assert
{
    /**
     * Middleware, указанный именем класса, существует и реализует MiddlewareInterface.
     * Готовый объект проверять не нужно — это делает тип параметра.
     *
     * @throws InvalidRoute
     */
    public static function middleware(MiddlewareInterface|string $middleware): void
    {
        if (\is_string($middleware)) {
            self::classExists($middleware, 'класс middleware %s не найден');
            self::implementsInterface($middleware, MiddlewareInterface::class, 'middleware %s должен реализовать %2$s');
        }
    }

    /**
     * @param string $message
     *
     * @throws InvalidRoute
     *
     * @psalm-pure
     */
    #[Override]
    protected static function reportInvalidArgument($message): never
    {
        throw InvalidRoute::because($message);
    }
}
