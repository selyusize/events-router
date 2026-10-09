# Воркеры и источники событий

`dispatch()` рассылает событие, которое вы создали в коде. Воркер делает то же самое, но события забирает сам из **источника**: очереди, вебхука, файла. Это аналог `$app->run()` в Slim.

```php
--8<-- "workers/worker.php:example"
```

```text
--8<-- "workers/worker.out"
```

## `run()`

```php
$processed = $events->run($source, $afterDispatch);
```

- Берёт события из источника по одному и для каждого вызывает `dispatch()`: те же маршруты, middleware и обработка ошибок.
- После каждой рассылки вызывает `$afterDispatch` с [отчётом](dispatching.md#report), если он передан. Здесь удобно логировать или считать ошибки.
- Возвращает число обработанных событий, когда источник закончился. Очередь может не заканчиваться никогда — тогда воркер работает до остановки процесса.

Исключение, которое вышло из `dispatch()` (из middleware роутера, из обработчика ошибок или при стратегии `ErrorStrategyEnum::Throw`), останавливает воркер и выходит из `run()`. Необработанные события остаются в источнике, а процесс перезапускает менеджер процессов (supervisor, systemd, Kubernetes).

## Источник событий

Источник реализует `Selyusize\EventsRouter\Contract\Source\EventSourceInterface` — один метод `events()`, который отдаёт события по одному. Проще всего написать его генератором: код после `yield` выполняется, когда событие уже разослано, и там можно подтвердить сообщение в очереди.

```php
<?php

use Selyusize\EventsRouter\Contract\Source\EventSourceInterface;
use Selyusize\EventsRouter\Event;

final class QueueEventSource implements EventSourceInterface
{
    public function __construct(private readonly Queue $queue) {}

    public function events(): iterable
    {
        while ($message = $this->queue->receive()) {
            yield new Event($message->getRoutingKey(), $message->getBody());

            $this->queue->acknowledge($message);   // событие разослано
        }
    }
}
```

Готовые источники для RabbitMQ и других очередей появятся отдельными пакетами.

## `InMemoryEventSource`

Очередь в памяти — для тестов и простых сценариев. События можно добавлять через `push()` прямо во время обработки: слушатель кладёт в очередь следующее событие, и тот же `run()` его разошлёт.

```php
use Selyusize\EventsRouter\Source\InMemoryEventSource;

$source = new InMemoryEventSource(new Event('order.created'));
$source->push(new Event('order.42.paid'));

$events->run($source);   // 2
```
