<?php

declare(strict_types=1);

/**
 * Заглушка CMain — класса глобального $APPLICATION в Bitrix — для тестов и примеров.
 */
final class CMain
{
    private CApplicationException|false $exception = false;

    /**
     * @param string $msg
     * @param false|string $id
     */
    public function ThrowException($msg, $id = false): void
    {
        $this->exception = new CApplicationException($msg);
    }

    public function GetException(): CApplicationException|false
    {
        return $this->exception;
    }
}

/**
 * Заглушка CApplicationException.
 */
final class CApplicationException
{
    public function __construct(
        private readonly string $message,
    ) {}

    public function GetString(): string
    {
        return $this->message;
    }
}
