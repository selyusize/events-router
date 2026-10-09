<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

/**
 * Номер версии объявленных маршрутов: растёт при каждом изменении.
 *
 * Группы и маршруты делят один экземпляр, а роутер по номеру понимает,
 * что готовую таблицу маршрутов пора пересобрать.
 *
 * @internal
 */
final class Revision
{
    private int $number = 0;

    public function bump(): void
    {
        ++$this->number;
    }

    public function get(): int
    {
        return $this->number;
    }
}
