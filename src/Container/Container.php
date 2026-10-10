<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Container;

use DI\Container as PhpDiContainer;
use Psr\Container\ContainerInterface;

/**
 * Контейнер роутера — статический фасад над PSR-11, как `Rasa\Container\Container`
 * в стартовой архитектуре.
 *
 * Из него роутер создаёт middleware, указанные именем класса. Слушатели статичные,
 * поэтому зависимости берут отсюда сами:
 *
 * ```php
 * final class SendConfirmationEmail implements ListenerInterface
 * {
 *     public static function handle(EventInterface $event): void
 *     {
 *         Container::get(Mailer::class)->sendOrderConfirmation($event->getAttribute('order_id'));
 *     }
 * }
 * ```
 *
 * Пока контейнер проекта не подключён (`EventRouterFactory::create($container)`),
 * работает PHP-DI с автосвязыванием: классы с зависимостями в конструкторе
 * создаются без регистрации.
 */
final class Container
{
    private static ?ContainerInterface $container = null;

    /**
     * Подключить контейнер проекта. Вызывается из EventRouterFactory::create($container).
     */
    public static function set(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * Подключённый контейнер, а если его нет — PHP-DI с автосвязыванием.
     */
    public static function getInstance(): ContainerInterface
    {
        return self::$container ??= new PhpDiContainer();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function get(string $class): object
    {
        /** @var T */
        return self::getInstance()->get($class);
    }
}
