# Configuration

## Factory

```php
EventRouterFactory::create(?ContainerInterface $container = null, array $config = []): EventRouter
```

| Argument | What it is | Default |
| --- | --- | --- |
| `$container` | the project's PSR-11 container: middleware is created from it, and `Container::get()` returns it | PHP-DI with autowiring |
| `$config` | router settings, keys below | `[]` |

## Config keys

Keys are snake_case, so the array can be taken as is from the project config.

| Key | Type | Default | What it does |
| --- | --- | --- | --- |
| `log_path` | non-empty string | `<sys_get_temp_dir()>/events-router/{date}.log` | log file path template, placeholders `{date}` (`Y-m-d`) and `{level}` (`error`, `info`, …) |
| `log_dispatch` | `bool` | `false` | log every dispatch: event, attributes, listeners with status and time |
| `route_cache_file` | non-empty string or `null` | `null` | PHP route cache file for `loadRoutes()`, see [Enable the route cache](../how-to/cache.md) |
| `locale` | `'ru'` or `'en'` | `'ru'` | language of exceptions, log and warnings, see [below](#locale) |

An unknown key or a value of the wrong type throws [`InvalidConfig`](../errors/invalid-config.md).

```php
$events = EventRouterFactory::create($container, [
    'log_path' => '/var/www/logs/events-router/{level}/{date}.log',
    'log_dispatch' => true,
    'route_cache_file' => '/var/www/var/cache/events-routes.php',
    'locale' => 'en',
]);
```

## Language of messages {#locale}

The library's messages are in Russian by default: exception texts, log entries, PHP warnings. `'locale' => 'en'` switches them to English, and links in exceptions lead to the English pages of this site:

```text
Invalid topic pattern "order-{order_id}.paid": parameter in segment "order-{order_id}" must take the whole segment, e.g. order.{order_id}. See https://selyusize.github.io/events-router/en/errors/invalid-topic-pattern/
```

The language is **one per process**. An event name is validated in `new Event()`, where there is no router, so the language cannot belong to a router instance. It applies from the `create()` call on, including to events created afterwards. Create the router at application bootstrap, before the first events. A `create()` call without the `locale` key does not change the language.

## Router setters

Things that are easier to configure with an object than with a config value.

| Method | What it does | Default |
| --- | --- | --- |
| `setLogger(LoggerInterface $logger)` | router log instead of the file from `log_path` | `FileLogger` |
| `setErrorHandler(ErrorHandlerInterface $handler)` | where to send listener errors | writing to the router log |
| `setErrorStrategy(ErrorStrategyEnum $strategy)` | what to do after a listener error | `Continue` |
| `setPrefix(string $prefix)` | common prefix of all topics | `''` |
| `add($middleware)` | router middleware, once per event | — |
