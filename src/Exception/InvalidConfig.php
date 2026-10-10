<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Exception;

use InvalidArgumentException;
use Selyusize\EventsRouter\Contract\Exception\ExceptionInterface;
use Selyusize\EventsRouter\Documentation;
use Selyusize\EventsRouter\Locale\Messages;

/**
 * В EventRouterFactory::create() передан неверный конфиг: неизвестный ключ или значение не того типа.
 */
final class InvalidConfig extends InvalidArgumentException implements ExceptionInterface
{
    public static function because(string $reason): self
    {
        return new self(\sprintf(
            Messages::translate('Неверный конфиг роутера: %s. См. %s'),
            $reason,
            Documentation::errorUrl('invalid-config'),
        ));
    }
}
