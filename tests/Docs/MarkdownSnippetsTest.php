<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Docs;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Вставка PHP-примера (`--8<-- "файл.php:кусок"`) должна стоять внутри блока ```php.
 *
 * Без блока Markdown разбирает код как текст: абзацы, `__DIR__` становится жирным,
 * часть строк пропадает. Сборка сайта в строгом режиме такое не ловит.
 *
 * @internal
 */
final class MarkdownSnippetsTest extends TestCase
{
    private const DOCS_DIRS = [__DIR__ . '/../../docs', __DIR__ . '/../../docs-en'];

    public function testPhpSnippetsAreInsideCodeBlocks(): void
    {
        $bare = [];

        foreach (self::DOCS_DIRS as $directory) {
            $root = realpath($directory);
            self::assertIsString($root);

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $relative = substr($file->getPathname(), \strlen($root) + 1);

                if ($file->getExtension() !== 'md' || str_starts_with($relative, 'api' . \DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $insideCode = false;

                foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
                    if (str_starts_with(ltrim($line), '```')) {
                        $insideCode = !$insideCode;

                        continue;
                    }

                    if (!$insideCode && preg_match('/^\s*--8<-- "[^"]+\.php[:"]/', $line) === 1) {
                        $bare[] = basename($root) . \DIRECTORY_SEPARATOR . $relative . ':' . ($number + 1);
                    }
                }
            }
        }

        self::assertSame([], $bare, 'PHP-вставка вне блока ```php');
    }
}
