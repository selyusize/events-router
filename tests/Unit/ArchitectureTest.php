<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Selyusize\EventsRouter\Exception\ExceptionInterface;
use SplFileInfo;
use Throwable;

/**
 * Проверяет соглашения, общие для всего кода библиотеки.
 *
 * @internal
 */
final class ArchitectureTest extends TestCase
{
    private const SOURCE_DIR = __DIR__ . '/../../src';
    private const ROOT_NAMESPACE = 'Selyusize\EventsRouter\\';

    public function testSourceDirectoryIsNotEmpty(): void
    {
        self::assertNotEmpty(self::declarations());
    }

    /**
     * @param class-string $name
     */
    #[DataProvider('provideDeclarationCases')]
    public function testConcreteClassesAreFinal(string $name): void
    {
        $reflection = new ReflectionClass($name);

        if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum() || $reflection->isAbstract()) {
            $this->expectNotToPerformAssertions();

            return;
        }

        self::assertTrue($reflection->isFinal(), \sprintf('Класс %s должен быть final', $name));
    }

    /**
     * @param class-string $name
     */
    #[DataProvider('provideDeclarationCases')]
    public function testExceptionsImplementLibraryInterface(string $name): void
    {
        $reflection = new ReflectionClass($name);

        if (!$reflection->implementsInterface(Throwable::class)) {
            $this->expectNotToPerformAssertions();

            return;
        }

        self::assertTrue(
            $reflection->implementsInterface(ExceptionInterface::class),
            \sprintf('Исключение %s должно реализовывать %s', $name, ExceptionInterface::class),
        );
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideDeclarationCases(): iterable
    {
        foreach (self::declarations() as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @return list<class-string>
     */
    private static function declarations(): array
    {
        $root = realpath(self::SOURCE_DIR);
        self::assertIsString($root);

        $names = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1, -\strlen('.php'));
            $name = self::ROOT_NAMESPACE . str_replace(\DIRECTORY_SEPARATOR, '\\', $relative);

            self::assertTrue(
                class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name),
                \sprintf('Файл %s должен объявлять %s (PSR-4)', $file->getPathname(), $name),
            );

            /** @var class-string $name */
            $names[] = $name;
        }

        sort($names);

        return $names;
    }
}
