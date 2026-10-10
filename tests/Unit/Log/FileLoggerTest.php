<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Log;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Selyusize\EventsRouter\Service\Log\FileLogger;

/**
 * @internal
 */
final class FileLoggerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/events-router-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            exec('rm -rf ' . escapeshellarg($this->directory));
        }
    }

    public function testSubstitutesLevelAndDateAndCreatesDirectories(): void
    {
        $logger = new FileLogger($this->directory . '/{level}/{date}.log');

        $logger->error('первая');
        $logger->info('вторая');

        $date = date('Y-m-d');
        self::assertStringContainsString('] ERROR первая', (string)file_get_contents($this->directory . '/error/' . $date . '.log'));
        self::assertStringContainsString('] INFO вторая', (string)file_get_contents($this->directory . '/info/' . $date . '.log'));
    }

    public function testWritesOneLinePerRecordWithJsonContext(): void
    {
        $file = $this->directory . '/events.log';
        $logger = new FileLogger($file);

        $logger->warning('с контекстом', ['event' => 'order.42.paid', 'attributes' => ['order_id' => '42']]);
        $logger->warning('без контекста');

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(2, $lines);
        self::assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] WARNING с контекстом \{"event":"order.42.paid","attributes":\{"order_id":"42"\}\}$/', $lines[0]);
        self::assertStringEndsWith('WARNING без контекста', $lines[1]);
    }

    public function testWritesExceptionClassMessageAndPlace(): void
    {
        $file = $this->directory . '/events.log';
        $error = new RuntimeException('сервис бонусов недоступен');

        (new FileLogger($file))->error('упал', ['exception' => $error]);

        $line = (string)file_get_contents($file);
        self::assertStringContainsString('"class":"RuntimeException"', $line);
        self::assertStringContainsString('"message":"сервис бонусов недоступен"', $line);
        self::assertStringContainsString('"file":"' . __FILE__ . ':' . $error->getLine() . '"', $line);
    }

    public function testWarnsInsteadOfThrowingWhenFileCannotBeWritten(): void
    {
        // Папку не создать: на месте родительской папки лежит файл
        mkdir($this->directory);
        touch($this->directory . '/file');
        $warnings = [];

        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_USER_WARNING);

        try {
            (new FileLogger($this->directory . '/file/{date}.log'))->error('потеряется');
        } finally {
            restore_error_handler();
        }

        self::assertSame(['events-router: не удалось создать папку для лога ' . $this->directory . '/file'], $warnings);
    }
}
