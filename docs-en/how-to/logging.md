# Set up the log

The event went out, and what happened next is unknown. Who received it? Who failed? How long did it take? Without a log you are left guessing. The router can record this itself: to a file, by day and level, one line per entry.

## Choose where to write

The log path is set when the router is created, with the `log_path` key. It is not just a path but a template:

```php
$events = EventRouterFactory::create($container, [
    'log_path' => '/var/www/logs/events-router/{level}/{date}.log',
]);
```

| Placeholder | Replaced with | Example |
| --- | --- | --- |
| `{date}` | entry date, `Y-m-d` | `2026-10-10` |
| `{level}` | PSR-3 level | `error`, `info` |

With this template errors end up in `…/error/2026-10-10.log` and the dispatch journal in `…/info/2026-10-10.log`. A new file every day, folders are created automatically.

Placeholders are optional. `'/var/log/events-router.log'` is a valid path too; everything just goes into one file.

Without `log_path` the log goes to the system temp folder: `<sys_get_temp_dir()>/events-router/{date}.log`. That is enough for development, not for production: set the path explicitly.

## Log every dispatch

By default only listener errors go to the log. To see all events, turn on `log_dispatch`:

```php
--8<-- "logging/dispatch-log.php:example"
```

```text
--8<-- "logging/dispatch-log.out"
```

Each event produces one `info` entry: name, number of listeners, total time. The context has every listener with its status (`Handled`, `Failed`, `Skipped`), time and the attributes it received, plus the error for a failed one.

Worth knowing:

- **An event without listeners is logged too**, as `listeners: 0`. This is the most common finding: the topic matched nothing because of a typo in the pattern or in the event name.
- **The payload is not logged.** It can be large and contain personal data. The log gets the event name and attributes.
- **With `ErrorStrategyEnum::Throw` there is no dispatch entry:** the exception leaves before the report is built. The calling code logs the error.

A convenient setup for projects: `log_dispatch` on in development and off in production, where there are many events and only errors matter.

Log messages are in Russian by default. With `'locale' => 'en'` in the config they are in English, as above.

## Write to your own logger

If the project already has Monolog or another PSR-3 logger set up, use it instead of the file:

```php
$events->setLogger($logger);   // any Psr\Log\LoggerInterface
```

Now listener errors and the dispatch journal go to your logger, and `log_path` is not used. `log_dispatch` keeps working.

## If the file cannot be written

No permissions on the folder or the disk is full: the dispatch does not fail because of that. The logger raises a PHP warning (`E_USER_WARNING`), and listeners keep working. The warning goes wherever PHP errors go in your environment.

## If the config has a mistake

An unknown key (`logPath` instead of `log_path`), an empty path or a non-`bool` `log_dispatch` throws [`InvalidConfig`](../errors/invalid-config.md) right when the router is created. A typo in a setting does not go unnoticed.

All config keys are in the [reference](../reference/config.md).
