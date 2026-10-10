<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Exception;

use InvalidArgumentException;
use Selyusize\EventsRouter\Contract\Exception\ExceptionInterface;
use Selyusize\EventsRouter\Documentation;
use Selyusize\EventsRouter\Locale\Messages;

/**
 * Маршрут объявлен с ошибкой: класс слушателя или middleware не найден
 * или не реализует нужный интерфейс.
 *
 * Бросается сразу в `listen()` или `add()`, а не при рассылке:
 * в стеке вызовов видна строка файла маршрутов с ошибкой.
 */
final class InvalidRoute extends InvalidArgumentException implements ExceptionInterface
{
    public static function because(string $reason): self
    {
        return new self(\sprintf(
            Messages::translate('Ошибка в маршрутах: %s. См. %s'),
            $reason,
            Documentation::errorUrl('invalid-route'),
        ));
    }
}
