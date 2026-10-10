<?php

declare(strict_types=1);

/*
 * Бенчмарк: events-router против symfony/event-dispatcher.
 *
 *     make bench
 *
 * Каждый сценарий выполняется в этом же процессе после прогрева: классы загружены,
 * регулярные выражения скомпилированы. Время — медиана из нескольких прогонов.
 *
 * Запускать с OPcache, как на проде: php -d opcache.enable_cli=1 -d opcache.file_update_protection=0.
 * Второй флаг нужен потому, что файл кэша маршрутов пишется в этом же процессе, а OPcache по умолчанию
 * не кэширует файлы, изменённые после начала запроса. На проде кэш читают следующие запросы.
 */

require __DIR__ . '/../vendor/autoload.php';

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\EventRouterFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

final class BenchListener implements ListenerInterface
{
    public static int $calls = 0;

    public static function handle(EventInterface $event): void
    {
        ++self::$calls;
    }

    public static function onSymfony(SymfonyEvent $event): void
    {
        ++self::$calls;
    }
}

final class BenchSymfonyEvent extends SymfonyEvent
{
    public function __construct(public readonly mixed $payload) {}
}

const EVENTS = 50;          // разных событий
const LISTENERS = 4;        // слушателей на событие
const DISPATCHES = 100_000; // рассылок в сценарии «рассылка»
const SETUPS = 300;         // запросов в сценарии «запрос»
const REQUEST_DISPATCHES = 3; // рассылок за запрос

/**
 * @param Closure(): void $scenario
 *
 * @return float медиана, секунды
 */
function measure(Closure $scenario, int $runs = 5): float
{
    $scenario();   // прогрев

    $times = [];

    for ($i = 0; $i < $runs; ++$i) {
        $start = hrtime(true);
        $scenario();
        $times[] = (hrtime(true) - $start) / 1e9;
    }

    sort($times);

    return $times[intdiv(\count($times), 2)];
}

/** @return list<string> */
function names(): array
{
    return array_map(static fn (int $i): string => 'shop.entity' . $i . '.changed', range(1, EVENTS));
}

/** Порядок событий для рассылки: одинаковый для обеих библиотек. */
function sequence(): array
{
    mt_srand(42);

    return array_map(static fn (): string => names()[mt_rand(0, EVENTS - 1)], range(1, DISPATCHES));
}

function symfony(): EventDispatcher
{
    $dispatcher = new EventDispatcher();

    foreach (names() as $name) {
        for ($j = 0; $j < LISTENERS; ++$j) {
            $dispatcher->addListener($name, [BenchListener::class, 'onSymfony']);
        }
    }

    return $dispatcher;
}

function router(array $config = []): EventRouter
{
    $events = EventRouterFactory::create(config: $config + ['log_path' => sys_get_temp_dir() . '/events-router-bench/{date}.log']);
    $events->loadRoutes(static function (EventRouter $events): void {
        foreach (names() as $name) {
            for ($j = 0; $j < LISTENERS; ++$j) {
                $events->listen($name, BenchListener::class);
            }
        }
    });

    return $events;
}

$sequence = sequence();
$cacheFile = sys_get_temp_dir() . '/events-router-bench/routes-' . getmypid() . '.php';
$results = [];

// ---------------------------------------------------------------- рассылка

$symfony = symfony();
$results['воркер: рассылка, точные имена'] = [
    'symfony/event-dispatcher' => measure(static function () use ($symfony, $sequence): void {
        foreach ($sequence as $name) {
            $symfony->dispatch(new BenchSymfonyEvent(['id' => 42]), $name);
        }
    }),
    'events-router' => measure(static function () use ($sequence): void {
        static $events = null;
        $events ??= router();

        foreach ($sequence as $name) {
            $events->dispatch(new Event($name, ['id' => 42]));
        }
    }),
];

// ---------------------------------------------------------------- подготовка

// Только собрать диспетчер со всеми слушателями, без рассылок
$results['подготовка: ' . EVENTS * LISTENERS . ' слушателей, без рассылок'] = [
    'symfony/event-dispatcher' => measure(static function (): void {
        for ($i = 0; $i < SETUPS; ++$i) {
            symfony();
        }
    }),
    'events-router' => measure(static function (): void {
        for ($i = 0; $i < SETUPS; ++$i) {
            router();
        }
    }),
    'events-router, кэш маршрутов' => measure(static function () use ($cacheFile): void {
        for ($i = 0; $i < SETUPS; ++$i) {
            router(['route_cache_file' => $cacheFile]);
        }
    }),
];

// ---------------------------------------------------------------- запрос PHP-FPM

// Каждый запрос PHP-FPM собирает диспетчер заново: подготовка + несколько рассылок
$request = 'запрос: ' . EVENTS * LISTENERS . ' слушателей + ' . REQUEST_DISPATCHES . ' рассылки';
$results[$request] = [
    'symfony/event-dispatcher' => measure(static function (): void {
        for ($i = 0; $i < SETUPS; ++$i) {
            $dispatcher = symfony();

            for ($j = 1; $j <= REQUEST_DISPATCHES; ++$j) {
                $dispatcher->dispatch(new BenchSymfonyEvent(null), 'shop.entity' . $j . '.changed');
            }
        }
    }),
    'events-router' => measure(static function (): void {
        for ($i = 0; $i < SETUPS; ++$i) {
            $events = router();

            for ($j = 1; $j <= REQUEST_DISPATCHES; ++$j) {
                $events->dispatch(new Event('shop.entity' . $j . '.changed'));
            }
        }
    }),
    'events-router, кэш маршрутов' => measure(static function () use ($cacheFile): void {
        for ($i = 0; $i < SETUPS; ++$i) {
            $events = router(['route_cache_file' => $cacheFile]);

            for ($j = 1; $j <= REQUEST_DISPATCHES; ++$j) {
                $events->dispatch(new Event('shop.entity' . $j . '.changed'));
            }
        }
    }),
];

@unlink($cacheFile);

// ---------------------------------------------------------------- вывод

$ops = ['воркер: рассылка, точные имена' => DISPATCHES];

printf("PHP %s, OPcache %s\n\n", PHP_VERSION, (function_exists('opcache_get_status') && opcache_get_status(false)) ? 'вкл' : 'выкл');

foreach ($results as $scenario => $libraries) {
    echo $scenario, PHP_EOL;
    $base = $libraries['symfony/event-dispatcher'];

    foreach ($libraries as $library => $seconds) {
        $count = $ops[$scenario] ?? SETUPS;
        printf("  %-32s %9.2f мкс на операцию  %5.2fx\n", $library, $seconds / $count * 1e6, $base / $seconds);
    }

    echo PHP_EOL;
}
