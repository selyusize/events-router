<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Log;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Tests\Fixture\Scripted\ScriptedListener;

/**
 * @internal
 */
final class DispatchLogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        ScriptedListener::reset();
        $this->directory = sys_get_temp_dir() . '/events-router-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->directory));
    }

    public function testWritesEventAttributesAndEveryListener(): void
    {
        $events = $this->router(true);
        $handled = ScriptedListener::define(static function (EventInterface $event): void {});
        $failed = ScriptedListener::define(static function (EventInterface $event): void {
            throw new RuntimeException('сервис бонусов недоступен');
        });
        $events->listen('order.{order_id}.paid', $handled);
        $events->listen('order.{order_id}.paid', $failed);

        $events->dispatch(new Event('order.42.paid', attributes: ['trace_id' => 'abc']));

        $record = $this->record('info');
        self::assertMatchesRegularExpression('/INFO events-router: order\.42\.paid, слушателей: 2, \d+\.\d мс /', $record['line']);
        self::assertSame(['trace_id' => 'abc'], $record['context']['attributes']);
        self::assertSame($handled, $record['context']['listeners'][0]['listener']);
        self::assertSame('Handled', $record['context']['listeners'][0]['status']);
        self::assertSame(['trace_id' => 'abc', 'order_id' => '42'], $record['context']['listeners'][0]['attributes']);
        self::assertArrayNotHasKey('error', $record['context']['listeners'][0]);
        self::assertSame('Failed', $record['context']['listeners'][1]['status']);
        self::assertSame('RuntimeException: сервис бонусов недоступен', $record['context']['listeners'][1]['error']);
    }

    public function testWritesEventWithoutListeners(): void
    {
        $this->router(true)->dispatch(new Event('order.unknown'));

        $line = (string)file_get_contents($this->directory . '/info/' . date('Y-m-d') . '.log');
        self::assertMatchesRegularExpression('/INFO events-router: order\.unknown, слушателей: 0, \d+\.\d мс$/', rtrim($line));
    }

    public function testWritesNothingByDefault(): void
    {
        $this->router(false)->dispatch(new Event('order.paid'));

        self::assertFileDoesNotExist($this->directory . '/info/' . date('Y-m-d') . '.log');
    }

    private function router(bool $logDispatch): EventRouter
    {
        return EventRouterFactory::create(config: [
            'log_path' => $this->directory . '/{level}/{date}.log',
            'log_dispatch' => $logDispatch,
        ]);
    }

    /**
     * @return array{line: string, context: array<string, mixed>}
     */
    private function record(string $level): array
    {
        $line = (string)file_get_contents($this->directory . '/' . $level . '/' . date('Y-m-d') . '.log');
        $json = substr($line, (int)strpos($line, ' {"'));

        /** @var array<string, mixed> $context */
        $context = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return ['line' => $line, 'context' => $context];
    }
}
