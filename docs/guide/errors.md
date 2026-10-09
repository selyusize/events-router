# Обработка ошибок

Исключение в слушателе или в его middleware не теряется и не ломает остальных слушателей:

1. роутер перехватывает его и записывает в отчёт со статусом `Failed`;
2. передаёт отчёт **обработчику ошибок**;
3. поступает по **стратегии**: продолжить, остановиться или выбросить исключение наружу.

## Обработчик ошибок

По умолчанию ошибки пишутся одной строкой в `error_log()` (`PhpErrorLogHandler`):

```text
events-router: слушатель App\Listener\AccrueBonuses упал на событии shop.order.42.paid: RuntimeException: сервис бонусов недоступен в /app/src/Listener/AccrueBonuses.php:21
```

Свой обработчик реализует `ErrorHandlerInterface` и получает исключение, событие с параметрами маршрута и класс упавшего слушателя.

--8<-- "errors/error-handling.php:handler"

```php
$events->setErrorHandler(EchoErrorHandler::class);   // или объект: new EchoErrorHandler()
```

Имя класса разрешается так же, как у middleware: из контейнера, а если контейнер его не знает — через `new`.

Для PSR-3 логгера есть готовый обработчик (нужен пакет `psr/log`):

```php
use Selyusize\EventsRouter\Service\Error\PsrLoggerErrorHandler;

$events->setErrorHandler(new PsrLoggerErrorHandler($logger));
```

Он передаёт в контекст `exception`, `event`, `attributes` и `listener`.

## Стратегии

| `ErrorStrategy` | Обработчик вызывается | Остальные слушатели | Исключение из `dispatch()` |
| --- | --- | --- | --- |
| `Continue` — по умолчанию | да | вызываются | нет |
| `Stop` | да | `Skipped` | нет |
| `Throw` | нет | не вызываются | да, исходное |

--8<-- "errors/error-handling.php:strategies"

```text
--8<-- "errors/error-handling.out"
```

`Throw` удобен в тестах и когда снаружи открыта транзакция, которую нужно откатить. Ошибку в этом случае обрабатывает вызывающий код, поэтому обработчик не вызывается — иначе она записалась бы дважды.

## Что не перехватывается

- **Исключение в middleware роутера** (`$events->add()`) не относится ни к одному слушателю и выходит из `dispatch()` при любой стратегии.
- **Исключение в самом обработчике ошибок** тоже выходит наружу: сломанный обработчик лучше заметить сразу.
- **Неверный обработчик** (класса нет, не тот интерфейс) — [`UnresolvableHandler`](../errors/unresolvable-handler.md) при первой ошибке слушателя.

## Остановка рассылки: PSR-14

Если событие или его payload реализует `Psr\EventDispatcher\StoppableEventInterface` и `isPropagationStopped()` вернул `true`, оставшиеся слушатели не вызываются и получают статус `Skipped`.

Остановку удобно выставлять на payload: это общий объект для всех копий события, поэтому флаг, выставленный одним слушателем, видят следующие.
