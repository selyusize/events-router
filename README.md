# events-router

Роутер событий для PHP. Маршруты событий описываются так же, как HTTP-маршруты в Slim: шаблоны топиков с параметрами, группы, middleware на группах и маршрутах. На одно событие можно повесить несколько слушателей. Совместим с PSR-14.

```php
return static function (EventRouter $events): void {
    $events->group('order', static function (RouteGroup $group): void {
        $group->listen('created', Listener\SendConfirmationEmail::class);
        $group->listen('{orderId}.paid', [
            Listener\MarkOrderPaid::class,
            Listener\AccrueBonuses::class,
        ]);
    })->add(Middleware\EventLogger::class);
};
```

> Статус: в разработке, API ещё не стабилен. План — в [plan.md](plan.md).

## Требования

PHP 8.1+

## Разработка

```bash
make install   # зависимости
make check     # всё, что проверяет CI: phplint, php-cs-fixer, Psalm, PHPUnit
make cs-fix    # исправить стиль кода

make docs-install  # один раз: Python-окружение для сайта документации
make docs-serve    # сайт документации локально: http://127.0.0.1:8000
```

Документация: https://selyusize.github.io/events-router/

В docker-окружении: `make check RUN="dl exec"`.

## Лицензия

MIT
