<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Exception;

use InvalidArgumentException;
use Selyusize\EventsRouter\Documentation;

/**
 * Имя события не является корректным топиком.
 *
 * Имя события — конкретный топик: непустые сегменты через точку,
 * без пробелов и без символов шаблонов `*`, `#`, `{`, `}`.
 */
final class InvalidEventName extends InvalidArgumentException implements ExceptionInterface
{
    public static function because(string $name, string $reason): self
    {
        return new self(\sprintf(
            'Некорректное имя события "%s": %s. См. %s',
            $name,
            $reason,
            Documentation::errorUrl('invalid-event-name'),
        ));
    }
}
