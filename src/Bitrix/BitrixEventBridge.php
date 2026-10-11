<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Bitrix;

use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;
use Closure;
use CMain;
use LogicException;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Dispatch\DispatchReport;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\Exception\InvalidRoute;
use Selyusize\EventsRouter\Locale\Messages;

/**
 * Мост от `EventManager` Bitrix к роутеру: события Bitrix приходят в слушателей
 * из файла маршрутов, как любые другие.
 *
 * Маршруты событий Bitrix — в группе `bitrix`, топик `bitrix.<модуль>.<Событие>`:
 *
 * ```php
 * $events->group('bitrix', static function (RouteGroup $bitrix): void {
 *     $bitrix->listen('main.OnAfterUserAdd', SendWelcomeEmail::class);
 *     $bitrix->listen('iblock.OnBeforeIBlockElementUpdate', ValidateProduct::class);
 * });
 * ```
 *
 * Подключение — одна строка в `local/php_interface/include/events.php`:
 *
 * ```php
 * BitrixEventBridge::attachLazy(
 *     EventManager::getInstance(),
 *     static fn (): EventRouter => Container::get(EventRouter::class),
 *     $_SERVER['DOCUMENT_ROOT'] . '/local/var/cache/bitrix-events.php',
 * );
 * ```
 *
 * На каждое событие Bitrix мост регистрирует один обработчик `[$bridge, 'bitrix.main.OnAfterUserAdd']`,
 * а когда событие происходит — рассылает `new Event('bitrix.main.OnAfterUserAdd', new BitrixEvent(...))`.
 * Слушатель читает и меняет данные через BitrixEvent.
 *
 * Свои события проекта рассылает dispatch() — тем же роутером, что и события Bitrix:
 *
 * ```php
 * BitrixEventBridge::dispatch(new Event('shop.order.42.paid', $order));
 * ```
 */
final class BitrixEventBridge
{
    /**
     * Последний подключённый мост: через него dispatch() находит роутер.
     */
    private static ?self $attached = null;

    private ?EventRouter $router = null;

    /**
     * @param Closure(): EventRouter $buildRouter
     * @param array<string, array{string, string}> $events топик → [модуль, событие]
     */
    private function __construct(
        EventManager $manager,
        private readonly Closure $buildRouter,
        private readonly array $events,
        private readonly string $prefix,
    ) {
        foreach ($events as $topic => [$moduleId, $eventType]) {
            $manager->addEventHandler($moduleId, $eventType, [$this, $topic]);
        }

        self::$attached = $this;
    }

    /**
     * Обработчик, который вызывает Bitrix: имя метода — топик события.
     *
     * Bitrix передаёт аргументы через call_user_func_array(), и __call() сохраняет ссылки:
     * `&$arFields` старого API остаётся ссылкой, а объект D7 приходит без предупреждений
     * о передаче по ссылке. Один обработчик работает с обоими стилями.
     *
     * @internal вызывается из Bitrix
     *
     * @param array<array-key, mixed> $arguments
     *
     * @return EventResult|false|null `false` или `EventResult::ERROR`, если слушатель отменил действие
     */
    public function __call(string $topic, array $arguments): EventResult|false|null
    {
        [$moduleId, $eventType] = $this->events[$topic];
        $bitrix = new BitrixEvent($moduleId, $eventType, $arguments);

        $this->router()->dispatch(new Event($topic, $bitrix));

        $reason = $bitrix->getCancelReason();

        if ($reason === null) {
            return null;
        }

        if ($bitrix->getD7Event() !== null) {
            return new EventResult(EventResult::ERROR, $reason, $moduleId);
        }

        // Старый API: текст ошибки Bitrix берёт из $APPLICATION->GetException()
        /** @var CMain|null $APPLICATION глобальный объект приложения Bitrix; вне Bitrix его нет */
        global $APPLICATION;

        if ($APPLICATION instanceof CMain) {
            $APPLICATION->ThrowException($reason);
        }

        return false;
    }

    /**
     * Разослать своё событие проекта тем же роутером, к которому подключён мост.
     *
     * В проекте на Bitrix нет общего места, где лежит роутер: события Bitrix приходят через мост,
     * а свои события рассылаются отсюда — из компонента, контроллера, агента или слушателя события
     * Bitrix. Маршруты, middleware, лог и обработка ошибок у них общие. После attachLazy() роутер
     * собирается при первом событии — своём или Bitrix — один раз на процесс.
     *
     * ```php
     * BitrixEventBridge::dispatch(new Event('shop.order.' . $order->getId() . '.paid', $order));
     * ```
     *
     * @throws LogicException если мост не подключён или топик события — под префиксом событий Bitrix
     */
    public static function dispatch(EventInterface $event): DispatchReport
    {
        $bridge = self::$attached;

        if ($bridge === null) {
            throw new LogicException(\sprintf(
                Messages::translate('Мост Bitrix не подключён: вызовите BitrixEventBridge::attachLazy() или attach() до рассылки события %s'),
                $event->getName(),
            ));
        }

        if (str_starts_with($event->getName(), $bridge->prefix . '.')) {
            throw new LogicException(\sprintf(
                Messages::translate('Событие %s — под префиксом событий Bitrix %s: такие события вызывает сам Bitrix. Дайте своему событию другой топик'),
                $event->getName(),
                $bridge->prefix,
            ));
        }

        return $bridge->router()->dispatch($event);
    }

    /**
     * Зарегистрировать обработчики сразу по маршрутам роутера.
     *
     * Подходит для разработки и консольных скриптов. На каждом хите сайта роутер
     * пришлось бы собирать целиком, поэтому на сайте используйте attachLazy().
     *
     * @param string $prefix с чего начинаются топики событий Bitrix, включая префикс роутера
     *
     * @throws InvalidRoute если маршрут под префиксом — не конкретное событие, а шаблон
     */
    public static function attach(EventManager $manager, EventRouter $router, string $prefix = 'bitrix'): void
    {
        new self($manager, static fn (): EventRouter => $router, self::events($router, $prefix), $prefix);
    }

    /**
     * Зарегистрировать обработчики по списку событий из файла, а роутер собрать,
     * только когда событие Bitrix действительно произошло.
     *
     * Нет файла — роутер собирается сразу, список событий записывается в файл.
     * После изменения маршрутов файл нужно удалить, например при деплое.
     *
     * @param Closure(): EventRouter $router
     * @param string $eventsFile PHP-файл со списком событий; папка создаётся сама
     *
     * @throws InvalidRoute если маршрут под префиксом — не конкретное событие, а шаблон
     */
    public static function attachLazy(EventManager $manager, Closure $router, string $eventsFile, string $prefix = 'bitrix'): void
    {
        if (is_file($eventsFile)) {
            /** @var array<string, array{string, string}> $events файл пишет этот же метод */
            $events = require $eventsFile;
        } else {
            // Роутер уже собран: мост возьмёт его, а не соберёт второй раз
            $built = $router();
            $router = static fn (): EventRouter => $built;
            $events = self::events($built, $prefix);

            // Через временный файл: параллельный хит не прочитает недописанный список
            $directory = \dirname($eventsFile);
            $temporary = $eventsFile . '.' . bin2hex(random_bytes(4)) . '.tmp';

            if ((is_dir($directory) || @mkdir($directory, 0o775, true)) && @file_put_contents($temporary, "<?php\n\nreturn " . var_export($events, true) . ";\n") !== false) {
                rename($temporary, $eventsFile);
            }
        }

        new self($manager, $router, $events, $prefix);
    }

    /**
     * Сегмент топика для модуля и события Bitrix — для `listen()` в группе `bitrix`.
     *
     * Точка в id партнёрского модуля — разделитель сегментов топика, поэтому она кодируется
     * как `~`: `topic('rasa.shop', 'OnOrderExport')` → `rasa~shop.OnOrderExport`.
     * Для модулей без точки (`main`, `iblock`, `sale`) можно писать топик руками.
     */
    public static function topic(string $moduleId, string $eventType): string
    {
        return str_replace('.', '~', $moduleId) . '.' . $eventType;
    }

    /**
     * Роутер, собранный один раз на процесс: для событий Bitrix и для dispatch().
     */
    private function router(): EventRouter
    {
        return $this->router ??= ($this->buildRouter)();
    }

    /**
     * События Bitrix из маршрутов роутера: топик → [модуль, событие].
     *
     * @return array<string, array{string, string}>
     *
     * @throws InvalidRoute
     */
    private static function events(EventRouter $router, string $prefix): array
    {
        $events = [];

        foreach ($router->getRoutes() as $route) {
            $topic = $route->getPattern()->getPattern();

            if (!str_starts_with($topic, $prefix . '.')) {
                continue;
            }

            $segments = explode('.', substr($topic, \strlen($prefix) + 1));

            if (\count($segments) !== 2 || strpbrk($topic, '*#{') !== false) {
                throw InvalidRoute::because(\sprintf(
                    Messages::translate('маршрут "%s": EventManager Bitrix подписывается только на конкретное событие, нужен топик %s.<модуль>.<Событие> без *, # и параметров'),
                    $topic,
                    $prefix,
                ));
            }

            $events[$topic] = [str_replace('~', '.', $segments[0]), $segments[1]];
        }

        return $events;
    }
}
