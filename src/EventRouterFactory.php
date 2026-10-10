<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

use Psr\Container\ContainerInterface;
use Selyusize\EventsRouter\Container\Container;
use Selyusize\EventsRouter\Exception\InvalidConfig;
use Selyusize\EventsRouter\Routing\Revision;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Service\Dispatcher;
use Selyusize\EventsRouter\Service\Log\FileLogger;
use Selyusize\EventsRouter\Service\RouteTableBuilder;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

/**
 * Создаёт роутер — аналог `AppFactory` в Slim. Единственное место,
 * где части роутера создаются и связываются друг с другом.
 *
 * ```php
 * $events = EventRouterFactory::create($container, [
 *     'log_path' => '/var/www/local/logs/events-router/{level}/{date}.log',
 *     'log_dispatch' => true,
 * ]);
 * (require __DIR__ . '/events.php')($events);
 * ```
 */
final class EventRouterFactory
{
    /**
     * Ключи конфига, в snake_case, как `config['events_router']` в стартовой архитектуре.
     */
    private const CONFIG_KEYS = ['log_path', 'log_dispatch'];

    /**
     * @param ContainerInterface|null $container контейнер проекта: из него создаются middleware,
     *                                           указанные именем класса, и его же отдаёт Container::get().
     *                                           Без него — PHP-DI с автосвязыванием.
     * @param array<string, mixed> $config настройки роутера:
     *                                     - `log_path` — шаблон пути к файлу лога с подстановками `{date}` и `{level}`,
     *                                     по умолчанию `<временная папка>/events-router/{date}.log`
     *                                     - `log_dispatch` — писать в лог каждую рассылку: событие, атрибуты,
     *                                     слушателей со статусом и временем; по умолчанию `false`
     *
     * @throws InvalidConfig если в конфиге неизвестный ключ или значение не того типа
     */
    public static function create(?ContainerInterface $container = null, array $config = []): EventRouter
    {
        $logPath = $config['log_path'] ?? sys_get_temp_dir() . '/events-router/{date}.log';
        $logDispatch = $config['log_dispatch'] ?? false;

        try {
            foreach (array_keys($config) as $key) {
                Assert::oneOf($key, self::CONFIG_KEYS, 'неизвестный ключ %s, допустимые: %2$s');
            }

            Assert::stringNotEmpty($logPath, 'log_path должен быть непустой строкой, передано %s');
            Assert::boolean($logDispatch, 'log_dispatch должен быть true или false, передано %s');
        } catch (InvalidArgumentException $error) {
            throw InvalidConfig::because($error->getMessage());
        }

        if ($container !== null) {
            Container::set($container);
        }

        return new EventRouter(
            new RouteGroup(new Revision()),
            new RouteTableBuilder(),
            new Dispatcher(Container::getInstance(), new FileLogger($logPath), $logDispatch),
        );
    }
}
