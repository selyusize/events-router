<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Exception;

use InvalidArgumentException;
use Selyusize\EventsRouter\Documentation;

/**
 * Шаблон топика в маршруте записан с ошибкой.
 *
 * Возникает при регистрации маршрута, а не при рассылке события,
 * поэтому ошибка в файле маршрутов видна сразу при старте.
 */
final class InvalidTopicPattern extends InvalidArgumentException implements ExceptionInterface
{
    public static function because(string $pattern, string $reason): self
    {
        return new self(\sprintf(
            'Некорректный шаблон топика "%s": %s. См. %s',
            $pattern,
            $reason,
            Documentation::errorUrl('invalid-topic-pattern'),
        ));
    }
}
