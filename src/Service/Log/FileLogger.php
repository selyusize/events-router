<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service\Log;

use DateTimeImmutable;
use Override;
use Psr\Log\AbstractLogger;
use Selyusize\EventsRouter\Locale\Messages;
use Stringable;
use Throwable;

/**
 * PSR-3 логгер в файл: одна запись — одна строка.
 *
 * Путь — шаблон с подстановками `{date}` (`2026-10-10`) и `{level}` (`error`, `info`, …),
 * поэтому файлы сами раскладываются по дням и уровням:
 *
 * ```php
 * new FileLogger('/var/www/local/logs/events-router/{level}/{date}.log');
 * ```
 *
 * ```text
 * [2026-10-10 14:03:12] ERROR events-router: слушатель … упал на событии shop.order.42.paid: … {"event":"shop.order.42.paid",…}
 * ```
 *
 * Папки создаются сами. Если файл записать не удалось, логгер выдаёт предупреждение PHP
 * (`E_USER_WARNING`), а рассылка продолжается.
 */
final class FileLogger extends AbstractLogger
{
    /**
     * @param non-empty-string $path шаблон пути к файлу с подстановками `{date}` и `{level}`
     */
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * @param mixed $level
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $now = new DateTimeImmutable();
        $level = \is_scalar($level) || $level instanceof Stringable ? (string)$level : get_debug_type($level);
        $file = strtr($this->path, ['{date}' => $now->format('Y-m-d'), '{level}' => $level]);

        // Исключение в json_encode() превратилось бы в {}: пишем главное о нём
        $context = array_map(static fn (mixed $value): mixed => $value instanceof Throwable ? [
            'class' => $value::class,
            'message' => $value->getMessage(),
            'file' => $value->getFile() . ':' . $value->getLine(),
            'trace' => $value->getTraceAsString(),
        ] : $value, $context);

        $line = \sprintf('[%s] %s %s', $now->format('Y-m-d H:i:s'), strtoupper($level), (string)$message);

        if ($context !== []) {
            $line .= ' ' . (string)json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        $directory = \dirname($file);

        // Папку мог создать параллельный процесс между is_dir() и mkdir() — поэтому проверка после
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            trigger_error(\sprintf(Messages::translate('events-router: не удалось создать папку для лога %s'), $directory), E_USER_WARNING);

            return;
        }

        if (@file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            trigger_error(\sprintf(Messages::translate('events-router: не удалось записать лог в %s'), $file), E_USER_WARNING);
        }
    }
}
