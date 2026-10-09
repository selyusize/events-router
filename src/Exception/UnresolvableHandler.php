<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Exception;

use LogicException;
use Selyusize\EventsRouter\Documentation;

/**
 * Класс из маршрута или настроек роутера нельзя использовать: его нет,
 * он не реализует нужный интерфейс или контейнер вернул что-то другое.
 *
 * Классы не загружаются при регистрации маршрутов, поэтому ошибка видна,
 * когда класс понадобился впервые. Для слушателя и middleware маршрута она
 * попадает в отчёт о рассылке, для middleware роутера и обработчика ошибок
 * выходит из dispatch() наружу.
 */
final class UnresolvableHandler extends LogicException implements ExceptionInterface
{
    /**
     * @param string $role кто это, с заглавной буквы: «Слушатель», «Middleware», «Обработчик ошибок»
     */
    public static function classNotFound(string $role, string $class): self
    {
        return self::because($role, $class, 'класс не найден, проверьте имя и автозагрузку');
    }

    /**
     * @param string $interface какой интерфейс нужен
     */
    public static function notImplementing(string $role, string $class, string $interface): self
    {
        return self::because($role, $class, 'класс не реализует ' . $interface);
    }

    public static function containerReturned(string $role, string $class, mixed $value, string $interface): self
    {
        return self::because($role, $class, \sprintf('контейнер вернул %s, а нужен %s', get_debug_type($value), $interface));
    }

    private static function because(string $role, string $class, string $reason): self
    {
        return new self(\sprintf(
            '%s "%s" нельзя использовать: %s. См. %s',
            $role,
            $class,
            $reason,
            Documentation::errorUrl('unresolvable-handler'),
        ));
    }
}
