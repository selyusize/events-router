<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;
use Selyusize\EventsRouter\Exception\UnresolvableHandler;

/**
 * Проверяет и создаёт то, что указано в маршрутах и настройках роутера.
 *
 * - Слушатели статичные: проверяется только, что класс есть и реализует ListenerInterface.
 * - Middleware и обработчик ошибок — объекты: если передан контейнер и он знает класс,
 *   объект берётся из контейнера, иначе создаётся через `new` без аргументов.
 *
 * Результаты запоминаются: каждый класс проверяется и создаётся один раз.
 *
 * @internal
 */
final class HandlerResolver
{
    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var array<string, true>
     */
    private array $checkedListeners = [];

    public function __construct(
        private readonly ?ContainerInterface $container = null,
    ) {}

    /**
     * @param class-string<ListenerInterface> $listener
     *
     * @return class-string<ListenerInterface>
     *
     * @throws UnresolvableHandler
     */
    public function listener(string $listener): string
    {
        if (!isset($this->checkedListeners[$listener])) {
            if (!class_exists($listener)) {
                throw UnresolvableHandler::classNotFound('Слушатель', $listener);
            }

            if (!is_subclass_of($listener, ListenerInterface::class)) {
                throw UnresolvableHandler::notImplementing('Слушатель', $listener, ListenerInterface::class);
            }

            $this->checkedListeners[$listener] = true;
        }

        return $listener;
    }

    /**
     * @param class-string<MiddlewareInterface>|MiddlewareInterface $middleware
     *
     * @throws UnresolvableHandler
     */
    public function middleware(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        return $this->resolve($middleware, MiddlewareInterface::class, 'Middleware');
    }

    /**
     * @param class-string<ErrorHandlerInterface>|ErrorHandlerInterface $handler
     *
     * @throws UnresolvableHandler
     */
    public function errorHandler(ErrorHandlerInterface|string $handler): ErrorHandlerInterface
    {
        return $this->resolve($handler, ErrorHandlerInterface::class, 'Обработчик ошибок');
    }

    /**
     * @template T of object
     *
     * @param string|T $reference объект или имя класса
     * @param class-string<T> $type
     *
     * @return T
     *
     * @throws UnresolvableHandler
     */
    private function resolve(object|string $reference, string $type, string $role): object
    {
        if (\is_object($reference)) {
            return $reference;
        }

        if (!isset($this->instances[$reference])) {
            $instance = $this->create($reference, $role);

            if (!$instance instanceof $type) {
                throw \is_object($instance) && $instance::class === $reference
                    ? UnresolvableHandler::notImplementing($role, $reference, $type)
                    : UnresolvableHandler::containerReturned($role, $reference, $instance, $type);
            }

            $this->instances[$reference] = $instance;
        }

        /** @var T проверено при создании */
        return $this->instances[$reference];
    }

    /**
     * Объект из контейнера, а если контейнер класс не знает — созданный через `new`.
     *
     * @throws UnresolvableHandler
     */
    private function create(string $class, string $role): mixed
    {
        if ($this->container !== null && $this->container->has($class)) {
            return $this->container->get($class);
        }

        if (!class_exists($class)) {
            throw UnresolvableHandler::classNotFound($role, $class);
        }

        return (new ReflectionClass($class))->newInstance();
    }
}
