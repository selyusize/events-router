# Ошибки

Каждое исключение библиотеки:

- реализует `Selyusize\EventsRouter\Contract\Exception\ExceptionInterface`, поэтому любую ошибку роутера можно поймать одним `catch`;
- содержит в тексте ссылку на свою страницу в этом разделе: что произошло, почему и как исправить.

```php
<?php

use Selyusize\EventsRouter\Contract\Exception\ExceptionInterface;

try {
    $events->dispatch($event);
} catch (ExceptionInterface $error) {
    // ошибка конфигурации или работы роутера, текст содержит ссылку на документацию
}
```

| Исключение | Когда возникает |
| --- | --- |
| [`InvalidEventName`](invalid-event-name.md) | имя события не является корректным топиком |
| [`InvalidTopicPattern`](invalid-topic-pattern.md) | шаблон топика в маршруте записан с ошибкой |
| [`UnmappableEvent`](unmappable-event.md) | в PSR-14 адаптер передан объект без `EventInterface`, а маппера нет |

Страницы появляются вместе с исключениями: CI не пропустит исключение без страницы.
