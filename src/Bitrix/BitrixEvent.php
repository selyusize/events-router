<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Bitrix;

use Bitrix\Main\Event as D7Event;
use Bitrix\Main\EventManager;
use Closure;
use LogicException;
use Override;
use Psr\EventDispatcher\StoppableEventInterface;
use ReflectionFunction;
use Selyusize\EventsRouter\Locale\Messages;

/**
 * Событие Bitrix — payload события роутера, которое пришло через BitrixEventBridge.
 *
 * ```php
 * final class ValidateProduct implements ListenerInterface
 * {
 *     public static function handle(EventInterface $event): void
 *     {
 *         $bitrix = $event->getPayload();
 *         Assert::isInstanceOf($bitrix, BitrixEvent::class);
 *
 *         if (($bitrix->getFields()['NAME'] ?? '') === '') {
 *             $bitrix->cancel('Название товара обязательно');
 *         }
 *     }
 * }
 * ```
 *
 * Bitrix вызывает обработчики двумя способами, и BitrixEvent поддерживает оба:
 *
 * | Стиль | Как пришли данные | Чем читать |
 * |-------|-------------------|------------|
 * | старый API (`GetModuleEvents()`, `&$arFields`) | позиционные аргументы | `getFields()`, `setField()`, `getArguments()` |
 * | D7 (`Bitrix\Main\Event::send()`) | объект `Bitrix\Main\Event` | `getD7Event()` |
 *
 * Это осознанное исключение из неизменяемости событий: `setField()` и `cancel()` меняют
 * данные, которые вернутся в Bitrix. Объект общий для всех слушателей события,
 * поэтому изменения одного слушателя видят следующие.
 *
 * После `cancel()` рассылка останавливается (PSR-14 `StoppableEventInterface`):
 * остальные слушатели получают статус `Skipped`.
 */
final class BitrixEvent implements StoppableEventInterface
{
    private ?string $cancelReason = null;

    /**
     * @internal событие создаёт BitrixEventBridge
     *
     * @param string $moduleId id модуля, как в Bitrix: `main`, `rasa.shop`
     * @param string $eventType имя события, как в Bitrix: `OnAfterUserAdd`
     * @param array<array-key, mixed> $arguments аргументы обработчика, как их передал Bitrix: со ссылками (`&$arFields`)
     *                                           для старого API или один `Bitrix\Main\Event` для D7
     */
    public function __construct(
        private readonly string $moduleId,
        private readonly string $eventType,
        private array $arguments,
    ) {}

    public function getModuleId(): string
    {
        return $this->moduleId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Все обработчики, которые Bitrix вызовет на это событие, в порядке вызова.
     *
     * Список берётся из `EventManager`, а не из маршрутов роутера, поэтому в нём все обработчики
     * любых неймспейсов: из `addEventHandler()` в `init.php`, зарегистрированные модулями
     * в `b_module_to_module` и сам мост роутера — по его месту видно, кто вызван до слушателей роутера.
     *
     * ```php
     * [
     *     'App\\Catalog\\ProductHandler::onBeforeUpdate',
     *     'CIBlockPropertyDirectory::onBeforeUpdate (iblock)',          // модуль из b_module_to_module
     *     'Closure /local/php_interface/init.php:42',
     *     'Selyusize\\EventsRouter\\Bitrix\\BitrixEventBridge::bitrix.iblock.OnBeforeIBlockElementUpdate',
     * ]
     * ```
     *
     * @return list<string> `Класс::метод`, имя функции, `Closure файл:строка` или путь к файлу обработчика;
     *                      у обработчиков модулей в скобках — id модуля
     */
    public function getHandlers(): array
    {
        $handlers = [];

        foreach (EventManager::getInstance()->findEventHandlers($this->moduleId, $this->eventType) as $handler) {
            /** @var mixed $callback callable из addEventHandler() или null у обработчика модуля */
            $callback = $handler['CALLBACK'] ?? null;

            if (\is_array($callback) && isset($callback[0], $callback[1]) && \is_string($callback[1])) {
                $class = \is_object($callback[0]) ? $callback[0]::class : (string)$callback[0];
                $name = ltrim($class, '\\') . '::' . $callback[1];
            } elseif ($callback instanceof Closure) {
                $function = new ReflectionFunction($callback);
                $name = 'Closure ' . (string)$function->getFileName() . ':' . (string)$function->getStartLine();
            } elseif (\is_object($callback)) {
                $name = $callback::class . '::__invoke';
            } elseif (\is_string($callback)) {
                $name = ltrim($callback, '\\');
            } elseif (($handler['TO_CLASS'] ?? '') !== '') {
                // Обработчик модуля из b_module_to_module: класс и метод — строками
                $name = ltrim((string)$handler['TO_CLASS'], '\\') . '::' . (string)($handler['TO_METHOD'] ?? '');
            } else {
                $name = (string)($handler['TO_PATH'] ?? '');
            }

            if (($handler['TO_MODULE_ID'] ?? '') !== '') {
                $name .= ' (' . (string)$handler['TO_MODULE_ID'] . ')';
            }

            $handlers[] = $name;
        }

        return $handlers;
    }

    /**
     * Объект события D7 или null, если событие пришло через старый API.
     */
    public function getD7Event(): ?D7Event
    {
        if (\count($this->arguments) === 1 && isset($this->arguments[0]) && $this->arguments[0] instanceof D7Event) {
            return $this->arguments[0];
        }

        return null;
    }

    /**
     * Позиционные аргументы старого API: `[$arFields]`, `[$ID, $arFields]` и т. п.
     *
     * @return list<mixed>
     */
    public function getArguments(): array
    {
        return array_values($this->arguments);
    }

    /**
     * Поля из первого аргумента старого API (`&$arFields`).
     *
     * @return array<array-key, mixed>
     *
     * @throws LogicException если первый аргумент не массив — например, у события D7
     */
    public function getFields(): array
    {
        $fields = $this->arguments[0] ?? null;

        if (!\is_array($fields)) {
            throw new LogicException(\sprintf(
                Messages::translate('У события %s:%s нет массива полей в первом аргументе. %s'),
                $this->moduleId,
                $this->eventType,
                $this->getD7Event() === null ? Messages::translate('Используйте getArguments().') : Messages::translate('Это событие D7: используйте getD7Event().'),
            ));
        }

        return $fields;
    }

    /**
     * Изменить поле в `&$arFields`: Bitrix получит новое значение.
     *
     * Работает в `OnBefore*`-событиях старого API, где Bitrix передаёт поля по ссылке.
     *
     * @throws LogicException если первый аргумент не массив
     */
    public function setField(string $name, mixed $value): void
    {
        $this->getFields();

        // Элемент — ссылка на переменную Bitrix: запись видна в вызывающем коде
        /** @psalm-suppress MixedArrayAssignment проверено в getFields() */
        $this->arguments[0][$name] = $value;
    }

    /**
     * Отменить действие в `OnBefore*`-событии.
     *
     * Bitrix получит отказ: в старом API — `false` и `$APPLICATION->ThrowException($reason)`,
     * в D7 — `EventResult::ERROR` с причиной в параметрах. Остальные слушатели не вызываются.
     */
    public function cancel(string $reason): void
    {
        $this->cancelReason = $reason;
    }

    /**
     * Причина отмены или null, если действие не отменяли.
     */
    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    #[Override]
    public function isPropagationStopped(): bool
    {
        return $this->cancelReason !== null;
    }
}
