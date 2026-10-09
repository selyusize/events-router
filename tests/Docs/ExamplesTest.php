<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Docs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Запускает все примеры из examples/, чтобы документация не расходилась с кодом.
 *
 * Пример должен завершиться с кодом 0. Если рядом лежит файл .out
 * с тем же именем, вывод примера должен совпасть с ним.
 *
 * @internal
 */
final class ExamplesTest extends TestCase
{
    private const EXAMPLES_DIR = __DIR__ . '/../../examples';

    public function testExamplesDirectoryIsNotEmpty(): void
    {
        self::assertNotEmpty(self::examples());
    }

    #[DataProvider('provideExampleRunsSuccessfullyCases')]
    public function testExampleRunsSuccessfully(string $path): void
    {
        [$exitCode, $stdout, $stderr] = self::runExample($path);

        self::assertSame(0, $exitCode, \sprintf("Пример %s завершился с кодом %d:\n%s", $path, $exitCode, $stderr . $stdout));

        $expectedOutputPath = substr($path, 0, -\strlen('.php')) . '.out';

        if (!is_file($expectedOutputPath)) {
            return;
        }

        self::assertSame(file_get_contents($expectedOutputPath), $stdout, \sprintf('Вывод примера %s не совпадает с %s', $path, $expectedOutputPath));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideExampleRunsSuccessfullyCases(): iterable
    {
        $root = (string)realpath(self::EXAMPLES_DIR);

        foreach (self::examples() as $path) {
            yield substr($path, \strlen($root) + 1) => [$path];
        }
    }

    /**
     * @return list<string>
     */
    private static function examples(): array
    {
        $root = realpath(self::EXAMPLES_DIR);
        self::assertIsString($root);

        $paths = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * @return array{int, string, string}
     */
    private static function runExample(string $path): array
    {
        $process = proc_open(
            [PHP_BINARY, $path],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            \dirname($path),
        );
        self::assertIsResource($process);

        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
