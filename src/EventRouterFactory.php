<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

use Psr\Container\ContainerInterface;
use Selyusize\EventsRouter\Container\Container;
use Selyusize\EventsRouter\Exception\InvalidConfig;
use Selyusize\EventsRouter\Locale\LocaleEnum;
use Selyusize\EventsRouter\Locale\Messages;
use Selyusize\EventsRouter\Routing\Revision;
use Selyusize\EventsRouter\Routing\RouteGroup;
use Selyusize\EventsRouter\Service\Dispatcher;
use Selyusize\EventsRouter\Service\Log\FileLogger;
use Selyusize\EventsRouter\Service\RouteCache;
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
 *     'route_cache_file' => '/var/www/local/var/cache/events-routes.php',   // в проде
 *     'locale' => 'en',
 * ]);
 * $events->loadRoutes(require __DIR__ . '/events.php');
 * ```
 */
final class EventRouterFactory
{
    /**
     * Ключи конфига, в snake_case, как `config['events_router']` в стартовой архитектуре.
     */
    private const CONFIG_KEYS = ['log_path', 'log_dispatch', 'route_cache_file', 'locale'];

    /**
     * @param ContainerInterface|null $container контейнер проекта: из него создаются middleware,
     *                                           указанные именем класса, и его же отдаёт Container::get().
     *                                           Без него — PHP-DI с автосвязыванием.
     * @param array<string, mixed> $config настройки роутера:
     *                                     - `log_path` — шаблон пути к файлу лога с подстановками `{date}` и `{level}`,
     *                                     по умолчанию `<временная папка>/events-router/{date}.log`
     *                                     - `log_dispatch` — писать в лог каждую рассылку: событие, атрибуты,
     *                                     слушателей со статусом и временем; по умолчанию `false`
     *                                     - `route_cache_file` — PHP-файл кэша маршрутов для loadRoutes();
     *                                     по умолчанию кэша нет
     *                                     - `locale` — язык исключений и лога: `ru` или `en`, по умолчанию `ru`.
     *                                     Общий на процесс: действует и на события, созданные после этого вызова;
     *                                     без ключа язык не меняется
     *
     * @throws InvalidConfig если в конфиге неизвестный ключ или значение не того типа
     */
    public static function create(?ContainerInterface $container = null, array $config = []): EventRouter
    {
        $logPath = $config['log_path'] ?? sys_get_temp_dir() . '/events-router/{date}.log';
        $logDispatch = $config['log_dispatch'] ?? false;
        $routeCacheFile = $config['route_cache_file'] ?? null;

        try {
            // Язык — первым, чтобы остальные ошибки конфига были уже на нём.
            // Без ключа язык не меняется: его мог задать другой вызов create()
            if (\array_key_exists('locale', $config)) {
                Assert::oneOf($config['locale'], array_column(LocaleEnum::cases(), 'value'), Messages::translate('locale должен быть одним из %2$s, передано %s'));
                /** @var value-of<LocaleEnum> $locale проверено строкой выше */
                $locale = $config['locale'];
                Messages::setLocale(LocaleEnum::from($locale));
            }

            foreach (array_keys($config) as $key) {
                Assert::oneOf($key, self::CONFIG_KEYS, Messages::translate('неизвестный ключ %s, допустимые: %2$s'));
            }

            Assert::stringNotEmpty($logPath, Messages::translate('log_path должен быть непустой строкой, передано %s'));
            Assert::boolean($logDispatch, Messages::translate('log_dispatch должен быть true или false, передано %s'));
            Assert::nullOrStringNotEmpty($routeCacheFile, Messages::translate('route_cache_file должен быть непустой строкой, передано %s'));
        } catch (InvalidArgumentException $error) {
            throw InvalidConfig::because($error->getMessage());
        }

        if ($container !== null) {
            Container::set($container);
        }

        $revision = new Revision();

        return new EventRouter(
            new RouteGroup($revision),
            $revision,
            new RouteTableBuilder(),
            new Dispatcher(Container::getInstance(), new FileLogger($logPath), $logDispatch),
            $routeCacheFile === null ? null : new RouteCache($routeCacheFile),
        );
    }
}
