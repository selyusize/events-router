# Ошибки

Каждое исключение библиотеки:

- реализует `Selyusize\EventsRouter\Exception\ExceptionInterface`, поэтому любую ошибку роутера можно поймать одним `catch`;
- содержит в тексте ссылку на свою страницу в этом разделе: что произошло, почему и как исправить.

```php
<?php

use Selyusize\EventsRouter\Exception\ExceptionInterface;

try {
    $events->dispatch($event);
} catch (ExceptionInterface $error) {
    // ошибка конфигурации или работы роутера, текст содержит ссылку на документацию
}
```

| Исключение | Когда возникает |
| --- | --- |
| [`InvalidEventName`](invalid-event-name.md) | имя события не является корректным топиком |

Страницы появляются вместе с исключениями: CI не пропустит исключение без страницы.
