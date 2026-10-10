<?php

declare(strict_types=1);

namespace Bitrix\Main;

/**
 * Заглушка Bitrix\Main\EventResult (D7) для тестов и примеров.
 */
final class EventResult
{
    public const UNDEFINED = 0;
    public const SUCCESS = 1;
    public const ERROR = 2;

    /**
     * @param int $type
     * @param mixed $parameters
     * @param string|null $moduleId
     * @param mixed $handler
     */
    public function __construct(
        private $type,
        private $parameters = null,
        private $moduleId = null,
        private $handler = null,
    ) {}

    public function getType(): int
    {
        return $this->type;
    }

    public function getParameters(): mixed
    {
        return $this->parameters;
    }

    public function getModuleId(): ?string
    {
        return $this->moduleId;
    }
}
