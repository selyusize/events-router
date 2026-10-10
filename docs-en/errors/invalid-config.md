# InvalidConfig

`Selyusize\EventsRouter\Exception\InvalidConfig`

`EventRouterFactory::create($container, $config)` got an invalid config.

## Why

The router checks the config right when it is created:

| Rule | Wrong | Right |
| --- | --- | --- |
| only known keys | `'logPath' => ...`, `'log_dir' => ...` | `'log_path' => ...` |
| `log_path` is a non-empty string | `'log_path' => ''` | `'log_path' => '/var/log/events-router/{date}.log'` |
| `log_dispatch` is `true` or `false` | `'log_dispatch' => 'yes'` | `'log_dispatch' => true` |
| `locale` is `'ru'` or `'en'` | `'locale' => 'de'`, `'locale' => 'EN'` | `'locale' => 'en'` |

An unknown key is an error, not a silent skip: that way a typo in a setting name does not go unnoticed.

## How to fix

Check the keys against the list in [Configuration](../reference/config.md): keys are written in snake_case.

```php
<?php

$events = EventRouterFactory::create($container, [
    'log_path' => __DIR__ . '/var/log/events-router/{level}/{date}.log',
]);
```
