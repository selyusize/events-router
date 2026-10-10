<?php

declare(strict_types=1);

namespace Bitrix\Main;

/**
 * Заглушка Bitrix\Main\Event (D7) для тестов и примеров.
 */
final class Event
{
    /**
     * @var list<EventResult>
     */
    private array $results = [];

    /**
     * @param string $moduleId
     * @param string $type
     * @param array<array-key, mixed> $parameters
     */
    public function __construct(
        private $moduleId,
        private $type,
        private $parameters = [],
    ) {}

    public function getModuleId(): string
    {
        return $this->moduleId;
    }

    public function getEventType(): string
    {
        return $this->type;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array-key $key
     */
    public function getParameter($key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    /**
     * @return list<EventResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function addResult(EventResult $result): void
    {
        $this->results[] = $result;
    }

    public function send(mixed $sender = null): void
    {
        EventManager::getInstance()->send($this);
    }
}
