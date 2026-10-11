<?php

declare(strict_types=1);

namespace Bitrix\Main;

/**
 * Заглушка Bitrix\Main\EventManager для тестов и примеров.
 *
 * Повторяет, как настоящий EventManager вызывает обработчики:
 * D7 (send()) — обработчику версии 2 передаётся объект Event, версии 1 — значения параметров;
 * старый API (GetModuleEvents() + ExecuteModuleEventEx()) — позиционные аргументы как есть, со ссылками.
 *
 * Обработчики из addEventHandler() хранят CALLBACK, обработчики модулей из registerEventHandler()
 * (в Bitrix — таблица b_module_to_module) — TO_MODULE_ID, TO_CLASS, TO_METHOD, TO_PATH.
 */
final class EventManager
{
    private static ?self $instance = null;

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    private array $handlers = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Только в заглушке: чистый EventManager для каждого теста.
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * @param string $fromModuleId
     * @param string $eventType
     * @param callable $callback
     * @param false|string $includeFile
     * @param int $sort
     */
    public function addEventHandler($fromModuleId, $eventType, $callback, $includeFile = false, $sort = 100): int
    {
        return $this->add($fromModuleId, $eventType, $callback, $sort, 2);
    }

    /**
     * @param string $fromModuleId
     * @param string $eventType
     * @param callable $callback
     * @param false|string $includeFile
     * @param int $sort
     */
    public function addEventHandlerCompatible($fromModuleId, $eventType, $callback, $includeFile = false, $sort = 100): int
    {
        return $this->add($fromModuleId, $eventType, $callback, $sort, 1);
    }

    /**
     * @param string $eventModuleId
     * @param string $eventType
     *
     * @return array<array-key, array<string, mixed>>
     */
    public function findEventHandlers($eventModuleId, $eventType, ?array $filter = null): array
    {
        return $this->handlers[strtoupper($eventModuleId) . ':' . strtoupper($eventType)] ?? [];
    }

    /**
     * Как в Bitrix: обработчик модуля записывается в b_module_to_module и вызывается на каждом хите.
     *
     * @param string $fromModuleId
     * @param string $eventType
     * @param string $toModuleId
     * @param string $toClass
     * @param string $toMethod
     * @param int $sort
     * @param string $toPath
     * @param array<array-key, mixed> $toMethodArg
     */
    public function registerEventHandler($fromModuleId, $eventType, $toModuleId, $toClass = '', $toMethod = '', $sort = 100, $toPath = '', $toMethodArg = []): void
    {
        $this->handlers[strtoupper($fromModuleId) . ':' . strtoupper($eventType)][] = [
            'FROM_MODULE_ID' => $fromModuleId,
            'MESSAGE_ID' => $eventType,
            'SORT' => $sort,
            'TO_MODULE_ID' => $toModuleId,
            'TO_PATH' => $toPath,
            'TO_CLASS' => $toClass,
            'TO_METHOD' => $toMethod,
            'TO_METHOD_ARG' => $toMethodArg,
            'VERSION' => 1,
        ];
    }

    public function send(Event $event): void
    {
        foreach ($this->findEventHandlers($event->getModuleId(), $event->getEventType()) as $handler) {
            $args = $handler['VERSION'] > 1 ? [$event] : array_values($event->getParameters());
            /** @var callable $callback */
            $callback = $handler['CALLBACK'] ?? [$handler['TO_CLASS'], $handler['TO_METHOD']];
            $result = \call_user_func_array($callback, $args);

            if ($result !== null && !$result instanceof EventResult) {
                $result = new EventResult(EventResult::UNDEFINED, $result);
            }

            if ($result !== null) {
                $event->addResult($result);
            }
        }
    }

    /**
     * @param callable $callback
     */
    private function add(string $fromModuleId, string $eventType, $callback, int $sort, int $version): int
    {
        $key = strtoupper($fromModuleId) . ':' . strtoupper($eventType);
        $this->handlers[$key][] = [
            'FROM_MODULE_ID' => $fromModuleId,
            'MESSAGE_ID' => $eventType,
            'CALLBACK' => $callback,
            'SORT' => $sort,
            'VERSION' => $version,
        ];

        return \count($this->handlers[$key]) - 1;
    }
}
