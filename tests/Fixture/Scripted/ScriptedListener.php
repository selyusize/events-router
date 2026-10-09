<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture\Scripted;

use Closure;
use LogicException;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

/**
 * Статичный слушатель, поведение которого задаёт тест.
 *
 * Слушатели статичные, поэтому параметры в них не передать через конструктор.
 * Вместо этого `define()` выдаёт следующий свободный класс-слот и запоминает для него сценарий.
 */
abstract class ScriptedListener implements ListenerInterface
{
    private const SLOTS = [
        Listener1::class,
        Listener2::class,
        Listener3::class,
        Listener4::class,
        Listener5::class,
        Listener6::class,
        Listener7::class,
        Listener8::class,
    ];

    /**
     * @var array<string, Closure(EventInterface): void>
     */
    private static array $scripts = [];

    /**
     * @param Closure(EventInterface): void $script
     *
     * @return class-string<ListenerInterface>
     */
    final public static function define(Closure $script): string
    {
        $class = self::SLOTS[\count(self::$scripts)] ?? throw new LogicException('Закончились слоты ScriptedListener');
        self::$scripts[$class] = $script;

        return $class;
    }

    final public static function reset(): void
    {
        self::$scripts = [];
    }

    final public static function handle(EventInterface $event): void
    {
        (self::$scripts[static::class] ?? throw new LogicException('Сценарий для ' . static::class . ' не задан'))($event);
    }
}
