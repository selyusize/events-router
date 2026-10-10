<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Exception;

use LogicException;
use Selyusize\EventsRouter\Contract\Exception\ExceptionInterface;
use Selyusize\EventsRouter\Documentation;
use Selyusize\EventsRouter\Locale\Messages;

/**
 * В PSR-14 адаптер передан объект, для которого неизвестно имя события:
 * он не реализует EventInterface, а функция-маппер не задана.
 */
final class UnmappableEvent extends LogicException implements ExceptionInterface
{
    public static function forObject(object $event): self
    {
        return new self(\sprintf(
            Messages::translate('Неизвестно имя события для объекта %s: реализуйте EventInterface или передайте маппер в Psr14EventDispatcher. См. %s'),
            get_debug_type($event),
            Documentation::errorUrl('unmappable-event'),
        ));
    }
}
