# events-router

Роутер событий для PHP. Маршруты событий описываются так же, как HTTP-маршруты в Slim: шаблоны топиков с параметрами, группы, middleware на группах и маршрутах. На одно событие можно повесить несколько слушателей. Совместим с PSR-14.

**Документация:** https://selyusize.github.io/events-router/

```php
return static function (EventRouter $events): void {
    $events->setPrefix('shop');

    $events->group('order', static function (RouteGroup $group): void {
        $group->listen('created', Listener\ReserveStock::class);
        $group->listen('created', Listener\SendConfirmationEmail::class);
        $group->listen('{order_id}.paid', Listener\MarkOrderPaid::class);
    })
        ->add(Middleware\EventLogger::class);
};
```

```php
$events = EventRouterFactory::create($container);   // контейнер необязателен: по умолчанию PHP-DI
$events->loadRoutes(require __DIR__ . '/events.php');

$report = $events->dispatch(new Event('shop.order.42.paid', ['amount' => 1500]));
```

```php
final class MarkOrderPaid implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        // $event->getAttribute('order_id') === '42'
    }
}
```

## Установка

```bash
composer require selyusize/events-router
```

PHP 8.1+. Версия 0.x: API может меняться до 1.0, изменения — в [CHANGELOG](CHANGELOG.md).

## Возможности

- шаблоны топиков: `order.{order_id}.paid`, `order.{id:\d+}`, `order.*.cancelled`, `order.#`;
- группы и middleware как в Slim, порядок слушателей — порядок строк в файле;
- отчёт о рассылке, изоляция ошибок слушателей, стратегии ошибок;
- контейнер из коробки (PHP-DI) или свой PSR-11;
- лог в файл по шаблону пути `{level}/{date}.log`, при желании — каждая рассылка со всеми слушателями;
- PSR-14 адаптер, воркеры с источниками событий.
- кэш маршрутов: запрос PHP-FPM с 200 слушателями в 1,8 раза быстрее `symfony/event-dispatcher` (`make bench`).

## Разработка

```bash
make install   # зависимости
make check     # всё, что проверяет CI: phplint, php-cs-fixer, Psalm, PHPUnit
make cs-fix    # исправить стиль кода

make docs-install  # один раз: Python-окружение для сайта документации
make docs-serve    # сайт документации локально: http://127.0.0.1:8000
```

В docker-окружении: `make check RUN="dl exec"`.

## Лицензия

MIT
