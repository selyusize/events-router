# Запустить воркер

`dispatch()` рассылает событие, которое вы создали в коде. Но часто события приходят снаружи: из очереди, вебхука, файла. Писать цикл «взял — разослал — подтвердил» в каждом проекте заново незачем. Для этого есть воркер, аналог `$app->run()` в Slim.

```php
--8<-- "workers/worker.php:example"
```

```text
--8<-- "workers/worker.out"
```

## Как работает `run()`

```php
$processed = $events->run($source, $afterDispatch);
```

- Берёт события из источника по одному и для каждого вызывает `dispatch()`. Маршруты, middleware и обработка ошибок те же, что при обычной рассылке.
- После каждой рассылки вызывает `$afterDispatch` с [отчётом](../reference/dispatch.md#report), если он передан. Тут удобно считать ошибки или писать метрики.
- Возвращает число обработанных событий, когда источник закончился. Очередь может не кончаться никогда — тогда воркер работает до остановки процесса.

Если исключение вышло из `dispatch()` (из middleware роутера, из обработчика ошибок или при стратегии `Throw`), воркер останавливается и выпускает его наружу. Необработанные события остаются в источнике, процесс перезапускает supervisor, systemd или Kubernetes. Упасть и подняться здесь честнее, чем молча продолжать в сломанном состоянии.

## Написать свой источник

Источник реализует `EventSourceInterface` — один метод `events()`, который отдаёт события по одному. Проще всего сделать его генератором: код после `yield` выполняется, когда событие уже разослано, и там можно подтвердить сообщение в очереди.

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

## Очередь в памяти

`InMemoryEventSource` — для тестов и простых сценариев. События можно добавлять прямо во время обработки: слушатель кладёт в очередь следующее событие, и тот же `run()` его разошлёт.

```php
use Selyusize\EventsRouter\Source\InMemoryEventSource;

$source = new InMemoryEventSource(new Event('order.created'));
$source->push(new Event('order.42.paid'));

$events->run($source);   // 2
```
