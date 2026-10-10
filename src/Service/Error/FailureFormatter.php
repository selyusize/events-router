<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service\Error;

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Locale\Messages;
use Throwable;

/**
 * Текст ошибки слушателя в одну строку: слушатель, событие, класс исключения, сообщение, место.
 *
 * ```text
 * events-router: слушатель App\Listener\AccrueBonuses упал на событии shop.order.42.paid:
 * RuntimeException: сервис бонусов недоступен в /app/src/Listener/AccrueBonuses.php:21
 * ```
 */
final class FailureFormatter
{
    public function format(Throwable $error, EventInterface $event, string $listener): string
    {
        return \sprintf(
            Messages::translate('events-router: слушатель %s упал на событии %s: %s: %s в %s:%d'),
            self::readableClass($listener),
            $event->getName(),
            get_debug_type($error),
            $error->getMessage(),
            $error->getFile(),
            $error->getLine(),
        );
    }

    /**
     * Имя класса без хвоста анонимного класса: после `@anonymous` в имени идёт
     * нулевой байт и путь к файлу, которым не место в строке лога.
     */
    public static function readableClass(string $class): string
    {
        $position = strpos($class, "\0");

        return $position === false ? $class : substr($class, 0, $position);
    }
}
