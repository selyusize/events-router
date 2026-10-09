<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Topic;

/**
 * Вид сегмента в шаблоне топика.
 *
 * @internal
 */
enum SegmentType
{
    /** Точное совпадение: `order` */
    case Literal;

    /** Параметр, значение сохраняется в атрибут: `{order_id}`, `{order_id:\d+}` */
    case Parameter;

    /** Ровно один любой сегмент: `*` */
    case AnySegment;

    /** Ноль или больше любых сегментов: `#` */
    case AnySegments;
}
