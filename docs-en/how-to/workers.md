# Run a worker

`dispatch()` sends an event you created in code. But events often come from outside: a queue, a webhook, a file. There is no reason to write the "take, dispatch, acknowledge" loop again in every project. The worker does it, the counterpart of `$app->run()` in Slim.

```php
--8<-- "workers/worker.php:example"
```

```text
--8<-- "workers/worker.out"
```

## How `run()` works

```php
$processed = $events->run($source, $afterDispatch);
```

- Takes events from the source one by one and calls `dispatch()` for each. Routes, middleware and error handling are the same as in a normal dispatch.
- After each dispatch calls `$afterDispatch` with the [report](../reference/dispatch.md#report), if given. This is a handy place to count errors or write metrics.
- Returns the number of processed events when the source runs out. A queue may never run out; then the worker runs until the process stops.

If an exception leaves `dispatch()` (from router middleware, from the error handler or with the `Throw` strategy), the worker stops and lets it out. Unprocessed events stay in the source, and supervisor, systemd or Kubernetes restarts the process. Crashing and restarting is more honest here than silently going on in a broken state.

## Write your own source

A source implements `EventSourceInterface`, a single `events()` method that yields events one by one. The easiest way is a generator: the code after `yield` runs when the event has already been dispatched, and that is where you can acknowledge the message in the queue.

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

            $this->queue->acknowledge($message);   // the event has been dispatched
        }
    }
}
```

Ready-made sources for RabbitMQ and other queues will come as separate packages.

## In-memory queue

`InMemoryEventSource` is for tests and simple scenarios. Events can be added right during processing: a listener puts the next event into the queue, and the same `run()` dispatches it.

```php
use Selyusize\EventsRouter\Source\InMemoryEventSource;

$source = new InMemoryEventSource(new Event('order.created'));
$source->push(new Event('order.42.paid'));

$events->run($source);   // 2
```
