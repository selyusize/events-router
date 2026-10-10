# Errors

Every library exception:

- implements `Selyusize\EventsRouter\Contract\Exception\ExceptionInterface`, so any router error can be caught with one `catch`;
- has a link in its message to its page in this section: what happened, why and how to fix it.

```php
<?php

use Selyusize\EventsRouter\Contract\Exception\ExceptionInterface;

try {
    $events->dispatch($event);
} catch (ExceptionInterface $error) {
    // a router configuration or runtime error; the message links to the documentation
}
```

| Exception | When it occurs |
| --- | --- |
| [`InvalidEventName`](invalid-event-name.md) | the event name is not a valid topic |
| [`InvalidTopicPattern`](invalid-topic-pattern.md) | a topic pattern in a route has a mistake |
| [`InvalidRoute`](invalid-route.md) | a listener or middleware class in a route is not found or does not implement the required interface |
| [`InvalidConfig`](invalid-config.md) | `EventRouterFactory::create()` got an unknown config key or a value of the wrong type |
| [`UnmappableEvent`](unmappable-event.md) | the PSR-14 adapter got an object without `EventInterface`, and there is no mapper |

Messages are in Russian by default; with `'locale' => 'en'` they are in English and link to these pages. See [Configuration](../reference/config.md#locale).

Pages come together with exceptions: CI does not let an exception through without a page.
