<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Dispatch;

/**
 * Чем закончился вызов одного слушателя.
 */
enum ListenerStatusEnum
{
    /** Слушатель отработал без исключений. */
    case Handled;

    /** Исключение в слушателе или в его middleware. */
    case Failed;

    /** Событие не дошло до слушателя: какой-то middleware не вызвал `$next`. */
    case Skipped;
}
