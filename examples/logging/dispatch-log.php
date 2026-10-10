<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void {}
}

final class AccrueBonuses implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        throw new RuntimeException('сервис бонусов недоступен');
    }
}

$logs = sys_get_temp_dir() . '/events-router-example-' . bin2hex(random_bytes(4));

// --8<-- [start:example]
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;

$events = EventRouterFactory::create(config: [
    'log_path' => $logs . '/{level}/{date}.log',   // ошибки и рассылки — в разные папки, файл на каждый день
    'log_dispatch' => true,                        // писать каждую рассылку
]);

$events->listen('shop.order.{order_id}.paid', MarkOrderPaid::class);
$events->listen('shop.order.{order_id}.paid', AccrueBonuses::class);

$events->dispatch(new Event('shop.order.42.paid', ['amount' => 1500]));
// --8<-- [end:example]

// Дата, время и миллисекунды меняются от запуска к запуску — заменяем их, чтобы вывод сравнивался с .out
foreach (['info', 'error'] as $level) {
    $line = (string)file_get_contents($logs . '/' . $level . '/' . date('Y-m-d') . '.log');
    $line = preg_replace(['/^\[[^\]]+\]/', '/\d+\.\d мс/', '/"ms":[\d.]+/', '/"trace":"[^"]*"/', '/"file":"[^"]*"/', '/ в \S+:\d+/'], ['[2026-10-10 14:03:12]', '0.4 мс', '"ms":0.1', '"trace":"…"', '"file":"…"', ' в …'], $line);

    echo $level, '/', '2026-10-10.log', PHP_EOL, $line, PHP_EOL;
}

exec('rm -rf ' . escapeshellarg($logs));
