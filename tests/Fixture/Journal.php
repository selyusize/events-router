<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

/**
 * Журнал вызовов: слушатели и middleware из фикстур пишут сюда, тесты сверяют порядок.
 */
final class Journal
{
    /**
     * @var list<string>
     */
    public static array $entries = [];

    public static function write(string $entry): void
    {
        self::$entries[] = $entry;
    }

    public static function reset(): void
    {
        self::$entries = [];
    }
}
