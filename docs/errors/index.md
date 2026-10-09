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

Пока исключений в библиотеке нет. Страницы появляются вместе с ними: CI не пропустит исключение без страницы.
