# Обработка ошибок

Исключение в слушателе или в его middleware не теряется и не ломает остальных слушателей:

1. роутер перехватывает его и записывает в отчёт со статусом `Failed`;
2. передаёт его **обработчику ошибок**;
3. поступает по **стратегии**: продолжить, остановиться или выбросить исключение наружу.

## Ошибки внутри слушателя

Обычно ошибку лучше поймать там, где понятно, что с ней делать, — в самом слушателе. Он знает, что пошло не так и что записать в лог:

```php
final class AccrueBonuses implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        try {
            Container::get(BonusService::class)->accrue($event->getAttribute('order_id'));
        } catch (BonusServiceUnavailable $error) {
            Container::get(LoggerInterface::class)->warning('Бонусы не начислены', ['exception' => $error]);
        }
    }
}
```

Такой слушатель завершается без исключения, и в отчёте у него статус `Handled`. Обработчик ошибок роутера — страховка для того, что слушатель не поймал.

## Обработчик ошибок

По умолчанию ошибки пишутся одной строкой в `error_log()` (`PhpErrorLogHandler`):

```text
events-router: слушатель App\Listener\AccrueBonuses упал на событии shop.order.42.paid: RuntimeException: сервис бонусов недоступен в /app/src/Listener/AccrueBonuses.php:21
```

Свой обработчик реализует `ErrorHandlerInterface` и получает исключение, событие с параметрами маршрута и класс упавшего слушателя.

```php
--8<-- "errors/error-handling.php:handler"
```

```php
$events->setErrorHandler(new EchoErrorHandler());
```

Обработчик — объект: он настраивается один раз, рядом с созданием роутера.

Для PSR-3 логгера есть готовый обработчик (нужен пакет `psr/log`):

```php
use Selyusize\EventsRouter\Service\Error\PsrLoggerErrorHandler;

$events->setErrorHandler(new PsrLoggerErrorHandler($logger));
```

Он передаёт в контекст `exception`, `event`, `attributes` и `listener`.

## Стратегии

| `ErrorStrategyEnum` | Обработчик вызывается | Остальные слушатели | Исключение из `dispatch()` |
| --- | --- | --- | --- |
| `Continue` — по умолчанию | да | вызываются | нет |
| `Stop` | да | `Skipped` | нет |
| `Throw` | нет | не вызываются | да, исходное |

```php
--8<-- "errors/error-handling.php:strategies"
```

```text
--8<-- "errors/error-handling.out"
```

`Throw` удобен в тестах и когда снаружи открыта транзакция, которую нужно откатить. Ошибку в этом случае обрабатывает вызывающий код, поэтому обработчик не вызывается — иначе она записалась бы дважды.

## Что не перехватывается

- **Исключение в middleware роутера** (`$events->add()`) не относится ни к одному слушателю и выходит из `dispatch()` при любой стратегии.
- **Исключение в самом обработчике ошибок** тоже выходит наружу: сломанный обработчик лучше заметить сразу.

## Остановка рассылки: PSR-14

Если событие или его payload реализует `Psr\EventDispatcher\StoppableEventInterface` и `isPropagationStopped()` вернул `true`, оставшиеся слушатели не вызываются и получают статус `Skipped`.

Остановку удобно выставлять на payload: это общий объект для всех копий события, поэтому флаг, выставленный одним слушателем, видят следующие.
