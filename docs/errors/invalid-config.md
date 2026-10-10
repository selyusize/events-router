# InvalidConfig

`Selyusize\EventsRouter\Exception\InvalidConfig`

В `EventRouterFactory::create($container, $config)` передан неверный конфиг.

## Почему

Роутер проверяет конфиг сразу при создании:

| Правило | Неверно | Верно |
| --- | --- | --- |
| только известные ключи | `'logPath' => ...`, `'log_dir' => ...` | `'log_path' => ...` |
| `log_path` — непустая строка | `'log_path' => ''` | `'log_path' => '/var/log/events-router/{date}.log'` |
| `log_dispatch` — `true` или `false` | `'log_dispatch' => 'yes'` | `'log_dispatch' => true` |

Неизвестный ключ — ошибка, а не молчаливый пропуск: так опечатка в имени настройки не остаётся незамеченной.

## Как исправить

Сверьте ключи со списком в [Конфигурации](../reference/config.md): ключи пишутся в snake_case.

```php
<?php

$events = EventRouterFactory::create($container, [
    'log_path' => __DIR__ . '/var/log/events-router/{level}/{date}.log',
]);
```
