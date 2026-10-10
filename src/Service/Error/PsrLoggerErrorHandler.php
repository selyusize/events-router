<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service\Error;

use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;
use Throwable;

/**
 * Запись ошибок слушателей в PSR-3 логгер.
 *
 * Обработчик по умолчанию: пишет в лог роутера (FileLogger или заданный через setLogger()).
 * Отдельно нужен, только чтобы писать ошибки в другой логгер или с другим уровнем.
 *
 * Исключение передаётся в контексте под ключом `exception`, как рекомендует PSR-3.
 *
 * ```php
 * $events->setErrorHandler(new PsrLoggerErrorHandler($logger));
 * ```
 */
final class PsrLoggerErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $level = LogLevel::ERROR,
        private readonly FailureFormatter $formatter = new FailureFormatter(),
    ) {}

    #[Override]
    public function handle(Throwable $error, EventInterface $event, string $listener): void
    {
        $this->logger->log($this->level, $this->formatter->format($error, $event, $listener), [
            'exception' => $error,
            'event' => $event->getName(),
            'attributes' => $event->getAttributes(),
            'listener' => FailureFormatter::readableClass($listener),
        ]);
    }
}
