<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service\Error;

use Override;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;
use Throwable;

/**
 * Обработчик ошибок по умолчанию: одна строка в `error_log()` на каждую ошибку.
 *
 * Нужен, чтобы ошибки слушателей не терялись молча, пока не настроен свой обработчик.
 */
final class PhpErrorLogHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly FailureFormatter $formatter = new FailureFormatter(),
    ) {}

    #[Override]
    public function handle(Throwable $error, EventInterface $event, string $listener): void
    {
        error_log($this->formatter->format($error, $event, $listener));
    }
}
