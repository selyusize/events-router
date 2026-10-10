# Конфигурация

## Фабрика

```php
EventRouterFactory::create(?ContainerInterface $container = null, array $config = []): EventRouter
```

| Аргумент | Что это | По умолчанию |
| --- | --- | --- |
| `$container` | PSR-11 контейнер проекта: из него создаются middleware, его же отдаёт `Container::get()` | PHP-DI с автосвязыванием |
| `$config` | настройки роутера, ключи ниже | `[]` |

## Ключи конфига

Ключи в snake_case — массив можно целиком взять из `config['events_router']` проекта.

| Ключ | Тип | По умолчанию | Что делает |
| --- | --- | --- | --- |
| `log_path` | непустая строка | `<sys_get_temp_dir()>/events-router/{date}.log` | шаблон пути к файлу лога, подстановки `{date}` (`Y-m-d`) и `{level}` (`error`, `info`, …) |
| `log_dispatch` | `bool` | `false` | писать каждую рассылку: событие, атрибуты, слушателей со статусом и временем |

Неизвестный ключ или значение не того типа — исключение [`InvalidConfig`](../errors/invalid-config.md).

```php
$events = EventRouterFactory::create($container, [
    'log_path' => '/var/www/local/logs/events-router/{level}/{date}.log',
    'log_dispatch' => true,
]);
```

## Сеттеры роутера

То, что удобнее настраивать объектом, а не значением в конфиге.

| Метод | Что делает | По умолчанию |
| --- | --- | --- |
| `setLogger(LoggerInterface $logger)` | лог роутера вместо файла из `log_path` | `FileLogger` |
| `setErrorHandler(ErrorHandlerInterface $handler)` | куда отправлять ошибки слушателей | запись в лог роутера |
| `setErrorStrategy(ErrorStrategyEnum $strategy)` | что делать после ошибки слушателя | `Continue` |
| `setPrefix(string $prefix)` | общий префикс всех топиков | `''` |
| `add($middleware)` | middleware роутера, один раз на событие | — |
