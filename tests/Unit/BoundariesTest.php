<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Зависимости между компонентами идут в одну сторону:
 *
 * ```text
 * Contract ← Topic ← Routing ← Dispatch ← Service ← EventRouter + EventRouterFactory
 * Contract ← Source (реализации источников событий)
 * Psr14 — адаптер поверх EventRouter, внешний слой, как и сам роутер
 * ```
 *
 * Компонент — первая часть неймспейса после Selyusize\EventsRouter
 * (Contract, Topic, …); для классов в корне неймспейса — сам класс.
 *
 * @internal
 */
final class BoundariesTest extends TestCase
{
    private const SOURCE_DIR = __DIR__ . '/../../src';
    private const ROOT_NAMESPACE = 'Selyusize\EventsRouter\\';

    /**
     * Компонент → от каких компонентов ему можно зависеть (кроме себя).
     * Компонента нет в списке — ему можно всё (фасад и точка сборки).
     */
    private const ALLOWED = [
        'Contract' => [],
        'Documentation' => [],
        'Exception' => ['Contract', 'Documentation'],
        'Event' => ['Contract', 'Exception'],
        'Topic' => ['Exception'],
        'Routing' => ['Contract', 'Topic', 'Exception'],
        'Dispatch' => ['Contract', 'Routing'],
        'Service' => ['Contract', 'Topic', 'Routing', 'Dispatch', 'Exception'],
        'Source' => ['Contract'],
    ];

    /**
     * Нарушения, которые ещё не исправлены. Список должен только сокращаться.
     */
    private const KNOWN_VIOLATIONS = [];

    public function testComponentsDependOnlyOnAllowedComponents(): void
    {
        $violations = [];

        foreach (self::files() as $component => $files) {
            if (!\array_key_exists($component, self::ALLOWED)) {
                continue;
            }

            foreach ($files as $file => $dependencies) {
                foreach ($dependencies as $dependency) {
                    if ($dependency !== $component && !\in_array($dependency, self::ALLOWED[$component], true)) {
                        $violations[] = $file . ' → ' . $dependency;
                    }
                }
            }
        }

        sort($violations);

        self::assertSame(self::KNOWN_VIOLATIONS, $violations, 'Нарушены границы компонентов (см. карту ALLOWED)');
    }

    /**
     * @return array<string, array<string, list<string>>> компонент → файл → компоненты, от которых он зависит
     */
    private static function files(): array
    {
        $root = realpath(self::SOURCE_DIR);
        self::assertIsString($root);

        $result = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1);
            $component = self::component(str_replace(\DIRECTORY_SEPARATOR, '\\', substr($relative, 0, -\strlen('.php'))));

            preg_match_all('/^use ' . preg_quote(self::ROOT_NAMESPACE, '/') . '([\w\\\]+);/m', (string)file_get_contents($file->getPathname()), $matches);

            $result[$component][$relative] = array_values(array_unique(array_map(self::component(...), $matches[1])));
        }

        return $result;
    }

    /**
     * `Routing\Route` → `Routing`, `Event` → `Event`.
     */
    private static function component(string $relativeClass): string
    {
        return explode('\\', $relativeClass)[0];
    }
}
