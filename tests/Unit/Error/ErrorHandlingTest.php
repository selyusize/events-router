<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Error;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Error\ErrorHandlerInterface;
use Selyusize\EventsRouter\Dispatch\ErrorStrategyEnum;
use Selyusize\EventsRouter\Dispatch\ListenerReport;
use Selyusize\EventsRouter\Dispatch\ListenerStatusEnum;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Exception\InvalidConfig;
use Selyusize\EventsRouter\Service\Error\FailureFormatter;
use Selyusize\EventsRouter\Service\Error\PsrLoggerErrorHandler;
use Selyusize\EventsRouter\Tests\Fixture\Journal;
use Selyusize\EventsRouter\Tests\Fixture\Scripted\ScriptedListener;
use Stringable;
use Throwable;

/**
 * @internal
 */
final class ErrorHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        Journal::reset();
        ScriptedListener::reset();
    }

    public function testDefaultHandlerWritesFailureToLogPath(): void
    {
        $directory = sys_get_temp_dir() . '/events-router-test-' . bin2hex(random_bytes(4));

        try {
            $events = EventRouterFactory::create(config: ['log_path' => $directory . '/{level}/{date}.log']);
            $listener = self::listener('broken', new RuntimeException('сервис бонусов недоступен'));
            $events->listen('order.{order_id}.paid', $listener);
            $events->dispatch(new Event('order.42.paid'));

            $written = (string)file_get_contents($directory . '/error/' . date('Y-m-d') . '.log');
        } finally {
            exec('rm -rf ' . escapeshellarg($directory));
        }

        self::assertStringContainsString(
            'ERROR events-router: слушатель ' . $listener . ' упал на событии order.42.paid: RuntimeException: сервис бонусов недоступен в ' . __FILE__,
            $written,
        );
        self::assertStringContainsString('"attributes":{"order_id":"42"}', $written);
    }

    public function testSetLoggerReplacesFileLog(): void
    {
        $logger = self::collectingLogger();

        $events = EventRouterFactory::create()->setLogger($logger);
        $events->listen('order.paid', self::listener('broken', new RuntimeException('сбой')));
        $events->dispatch(new Event('order.paid'));

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::ERROR, $logger->records[0][0]);
    }

    public function testCustomHandlerTakesPrecedenceOverLogger(): void
    {
        $logger = self::collectingLogger();
        $handler = self::collectingHandler();

        $events = EventRouterFactory::create()->setErrorHandler($handler)->setLogger($logger);
        $events->listen('order.paid', self::listener('broken', new RuntimeException('сбой')));
        $events->dispatch(new Event('order.paid'));

        self::assertSame([], $logger->records);
        self::assertCount(1, $handler->failures);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideInvalidConfigIsRejectedCases')]
    public function testInvalidConfigIsRejected(array $config, string $message): void
    {
        $this->expectException(InvalidConfig::class);
        $this->expectExceptionMessage($message);

        EventRouterFactory::create(config: $config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidConfigIsRejectedCases(): iterable
    {
        yield 'неизвестный ключ' => [['logPath' => '/tmp/x.log'], 'неизвестный ключ "logPath", допустимые: "log_path", "log_dispatch"'];
        yield 'log_dispatch не bool' => [['log_dispatch' => 'yes'], 'log_dispatch должен быть true или false'];
        yield 'пустой путь' => [['log_path' => ''], 'log_path должен быть непустой строкой'];
        yield 'путь не строка' => [['log_path' => 42], 'log_path должен быть непустой строкой, передано integer'];
    }

    public function testCustomHandlerReceivesEachFailureInOrder(): void
    {
        $handler = self::collectingHandler();

        $events = EventRouterFactory::create()->setErrorHandler($handler);
        $first = self::listener('first', new RuntimeException('первая'));
        $events->listen('order.{order_id}.paid', $first);
        $events->listen('order.{order_id}.paid', self::listener('ok'));
        $events->listen('order.{order_id}.paid', self::listener('third', new RuntimeException('вторая')));

        $report = $events->dispatch(new Event('order.42.paid'));

        self::assertSame(['первая', 'вторая'], array_map(static fn (array $failure): string => $failure[0]->getMessage(), $handler->failures));
        self::assertSame('42', $handler->failures[0][1]->getAttribute('order_id'), 'событие — с параметрами маршрута');
        self::assertSame($first, $handler->failures[0][2]);
        self::assertSame(
            array_map(static fn (ListenerReport $failure): ?Throwable => $failure->getError(), $report->getFailures()),
            array_map(static fn (array $failure): Throwable => $failure[0], $handler->failures),
        );
        self::assertSame(['first {}', 'ok {}', 'third {}'], Journal::$entries);
    }

    public function testStopStrategySkipsRemainingListeners(): void
    {
        $handler = self::collectingHandler();

        $events = EventRouterFactory::create()->setErrorHandler($handler)->setErrorStrategy(ErrorStrategyEnum::Stop);
        $events->listen('order.paid', self::listener('first'));
        $events->listen('order.paid', self::listener('broken', new RuntimeException('сбой')));
        $events->listen('order.paid', self::listener('third'));

        $report = $events->dispatch(new Event('order.paid'));

        self::assertSame(['first {}', 'broken {}'], Journal::$entries);
        self::assertSame([ListenerStatusEnum::Handled, ListenerStatusEnum::Failed, ListenerStatusEnum::Skipped], self::statuses($report->getListeners()));
        self::assertCount(1, $handler->failures);
    }

    public function testThrowStrategyRethrowsWithoutCallingHandler(): void
    {
        $handler = self::collectingHandler();
        $error = new RuntimeException('сбой');

        $events = EventRouterFactory::create()->setErrorHandler($handler)->setErrorStrategy(ErrorStrategyEnum::Throw);
        $events->listen('order.paid', self::listener('broken', $error));
        $events->listen('order.paid', self::listener('never'));

        try {
            $events->dispatch(new Event('order.paid'));
            self::fail('Ожидалось исключение');
        } catch (RuntimeException $thrown) {
            self::assertSame($error, $thrown);
        }

        self::assertSame(['broken {}'], Journal::$entries);
        self::assertSame([], $handler->failures);
    }

    public function testErrorSettingsDoNotLeakBetweenRouters(): void
    {
        $handler = self::collectingHandler();

        EventRouterFactory::create()->setErrorHandler($handler)->setErrorStrategy(ErrorStrategyEnum::Throw);

        $events = EventRouterFactory::create()->setErrorHandler($handler);
        $events->listen('order.paid', self::listener('broken', new RuntimeException('сбой')));
        $events->listen('order.paid', self::listener('next'));

        $events->dispatch(new Event('order.paid'));

        self::assertSame(['broken {}', 'next {}'], Journal::$entries, 'у второго роутера стратегия по умолчанию — Continue');
    }

    public function testStoppablePayloadStopsPropagation(): void
    {
        $payload = new class implements StoppableEventInterface {
            public bool $stopped = false;

            public function isPropagationStopped(): bool
            {
                return $this->stopped;
            }
        };

        $stopper = ScriptedListener::define(static function (EventInterface $event): void {
            Journal::write('stopper');
            $payload = $event->getPayload();
            \assert(\is_object($payload) && property_exists($payload, 'stopped'));
            $payload->stopped = true;
        });

        $events = EventRouterFactory::create();
        $events->listen('order.paid', self::listener('first'));
        $events->listen('order.paid', $stopper);
        $events->listen('order.paid', self::listener('never'));

        $report = $events->dispatch(new Event('order.paid', $payload));

        self::assertSame(['first {}', 'stopper'], Journal::$entries);
        self::assertSame([ListenerStatusEnum::Handled, ListenerStatusEnum::Handled, ListenerStatusEnum::Skipped], self::statuses($report->getListeners()));
    }

    public function testAlreadyStoppedEventReachesNoListener(): void
    {
        $payload = new class implements StoppableEventInterface {
            public function isPropagationStopped(): bool
            {
                return true;
            }
        };

        $events = EventRouterFactory::create();
        $events->listen('order.paid', self::listener('never'));

        $report = $events->dispatch(new Event('order.paid', $payload));

        self::assertSame([], Journal::$entries);
        self::assertSame([ListenerStatusEnum::Skipped], self::statuses($report->getListeners()));
    }

    public function testPsrLoggerHandlerLogsWithContext(): void
    {
        $logger = self::collectingLogger();
        $error = new RuntimeException('сбой');

        $events = EventRouterFactory::create()->setErrorHandler(new PsrLoggerErrorHandler($logger, LogLevel::CRITICAL));
        $listener = self::listener('broken', $error);
        $events->listen('order.{order_id}.paid', $listener);

        $events->dispatch(new Event('order.42.paid'));

        self::assertCount(1, $logger->records);
        [$level, $message, $context] = $logger->records[0];
        self::assertSame(LogLevel::CRITICAL, $level);
        self::assertStringContainsString('упал на событии order.42.paid', $message);
        self::assertSame($error, $context['exception']);
        self::assertSame('order.42.paid', $context['event']);
        self::assertSame(['order_id' => '42'], $context['attributes']);
        self::assertSame($listener, $context['listener']);
    }

    public function testFormatterCutsAnonymousClassNameBeforeNullByte(): void
    {
        $anonymous = (new class {})::class;

        self::assertStringContainsString("\0", $anonymous);
        self::assertStringNotContainsString("\0", FailureFormatter::readableClass($anonymous));
        self::assertStringEndsWith('@anonymous', FailureFormatter::readableClass($anonymous));
    }

    /**
     * @return AbstractLogger&object{records: list<array{mixed, string, array<array-key, mixed>}>}
     */
    private static function collectingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /**
             * @var list<array{mixed, string, array<array-key, mixed>}>
             */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string)$message, $context];
            }
        };
    }

    /**
     * @return ErrorHandlerInterface&object{failures: list<array{Throwable, EventInterface, string}>}
     */
    private static function collectingHandler(): ErrorHandlerInterface
    {
        return new class implements ErrorHandlerInterface {
            /**
             * @var list<array{Throwable, EventInterface, string}>
             */
            public array $failures = [];

            public function handle(Throwable $error, EventInterface $event, string $listener): void
            {
                $this->failures[] = [$error, $event, $listener];
            }
        };
    }

    /**
     * Статичный слушатель: пишет в журнал имя и атрибуты без order_id, при необходимости бросает исключение.
     *
     * @return class-string<ListenerInterface>
     */
    private static function listener(string $name, ?RuntimeException $error = null): string
    {
        return ScriptedListener::define(static function (EventInterface $event) use ($name, $error): void {
            Journal::write($name . ' ' . json_encode(array_diff_key($event->getAttributes(), ['order_id' => 1]), JSON_FORCE_OBJECT));

            if ($error !== null) {
                throw $error;
            }
        });
    }

    /**
     * @param list<ListenerReport> $reports
     *
     * @return list<ListenerStatusEnum>
     */
    private static function statuses(array $reports): array
    {
        return array_map(static fn (ListenerReport $report): ListenerStatusEnum => $report->getStatus(), $reports);
    }
}
