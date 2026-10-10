<?php

declare(strict_types=1);

namespace Bitrix\Main;

/**
 * Заглушка Bitrix\Main\EventManager для тестов и примеров.
 *
 * Повторяет, как настоящий EventManager вызывает обработчики:
 * D7 (send()) — обработчику версии 2 передаётся объект Event, версии 1 — значения параметров;
 * старый API (GetModuleEvents() + ExecuteModuleEventEx()) — позиционные аргументы как есть, со ссылками.
 */
final class EventManager
{
    private static ?self $instance = null;

    /**
     * @var array<string, list<array{FROM_MODULE_ID: string, MESSAGE_ID: string, CALLBACK: callable, SORT: int, VERSION: int}>>
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
     * @return list<array{FROM_MODULE_ID: string, MESSAGE_ID: string, CALLBACK: callable, SORT: int, VERSION: int}>
     */
    public function findEventHandlers($eventModuleId, $eventType, ?array $filter = null): array
    {
        return $this->handlers[strtoupper($eventModuleId) . ':' . strtoupper($eventType)] ?? [];
    }

    public function send(Event $event): void
    {
        foreach ($this->findEventHandlers($event->getModuleId(), $event->getEventType()) as $handler) {
            $args = $handler['VERSION'] > 1 ? [$event] : array_values($event->getParameters());
            $result = \call_user_func_array($handler['CALLBACK'], $args);

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
