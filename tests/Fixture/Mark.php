<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

/**
 * Зависимость для MarkingMiddleware: проверяет, что контейнер создаёт middleware с аргументами конструктора.
 */
final class Mark
{
    public function text(): string
    {
        return 'mark';
    }
}
