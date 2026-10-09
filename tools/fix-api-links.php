<?php

declare(strict_types=1);

/**
 * Чинит ссылки в сгенерированном справочнике API (docs/api).
 *
 * Шаблон phpDocumentor ставит ссылку на страницу любого класса, даже если это
 * класс PHP (InvalidArgumentException) или чужой пакет (PSR). Таких страниц
 * в справочнике нет, и сборка сайта в строгом режиме падает. Скрипт:
 *
 * - встроенные классы PHP ведёт на php.net;
 * - остальные внешние классы оставляет кодом без ссылки.
 *
 * Использование: php tools/fix-api-links.php docs/api
 */
$root = $argv[1] ?? '';

if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Использование: php tools/fix-api-links.php <каталог справочника>\n");

    exit(1);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'md') {
        continue;
    }

    $path = $file->getPathname();
    $content = (string)file_get_contents($path);

    $fixed = preg_replace_callback(
        '/\[`([^`]+)`\]\(([^)\s]+\.md)\)/',
        static function (array $match) use ($path): string {
            [$link, $name, $target] = $match;

            if (is_file(dirname($path) . '/' . $target)) {
                return $link;
            }

            $class = ltrim($name, '\\');

            if ((class_exists($class) || interface_exists($class)) && (new ReflectionClass($class))->isInternal()) {
                return sprintf('[`%s`](https://www.php.net/manual/ru/class.%s.php)', $name, strtolower($class));
            }

            return sprintf('`%s`', $name);
        },
        $content,
    );

    if ($fixed !== null && $fixed !== $content) {
        file_put_contents($path, $fixed);
    }
}
