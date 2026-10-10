# Handle listener errors

A listener failed. The bonus service is down, the database did not answer, unexpected data arrived. The question is not whether this happens, but what happens next.

By default the router does this:

1. catches the exception and records it in the report with the `Failed` status;
2. writes it to the [router log](logging.md);
3. calls the next listeners as if nothing happened.

One breakage does not turn into a chain reaction. Below is how to adjust this.

## Catch the error in the listener itself

The best place to handle an error is where its meaning is clear. The listener knows what went wrong and what to do about it:

```php
final class AccrueBonuses implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        try {
            Container::get(BonusService::class)->accrue($event->getAttribute('order_id'));
        } catch (BonusServiceUnavailable $error) {
            Container::get(LoggerInterface::class)->warning('Bonuses not accrued', ['exception' => $error]);
        }
    }
}
```

Such a listener finishes without an exception and gets the `Handled` status in the report. The router's error handling is a safety net for what the listener did not catch, not the main path.

## Send errors somewhere else

To send errors to monitoring, Sentry or a chat instead of the log, write your own handler. It implements `ErrorHandlerInterface` and receives the exception, the event with route parameters and the class of the failed listener:

```php
--8<-- "errors/error-handling.php:handler"
```

```php
$events->setErrorHandler(new EchoErrorHandler());
```

Your handler replaces writing to the log. If you need both, call the logger inside your handler.

If you only need a different logger or level, no custom class is needed:

```php
use Psr\Log\LogLevel;
use Selyusize\EventsRouter\Service\Error\PsrLoggerErrorHandler;

$events->setErrorHandler(new PsrLoggerErrorHandler($alertLogger, LogLevel::CRITICAL));
```

## Stop at the first error

Sometimes you cannot go on: if the payment failed, sending a "thank you for your purchase" email makes no sense. That is what strategies are for:

```php
--8<-- "errors/error-handling.php:strategies"
```

```text
--8<-- "errors/error-handling.out"
```

| Strategy | What happens |
| --- | --- |
| `ErrorStrategyEnum::Continue` | default: the error is recorded, other listeners run |
| `ErrorStrategyEnum::Stop` | the error is recorded, the remaining listeners get the `Skipped` status |
| `ErrorStrategyEnum::Throw` | the exception leaves `dispatch()` as is, the handler is not called |

`Throw` suits tests and cases where a transaction is open outside and must be rolled back. The calling code handles the error, so the router does not log it: otherwise it would end up in the log twice.

## Stop the dispatch without an error {#stop}

A listener may decide the others do not need the event. For that the event or its payload implements PSR-14 `StoppableEventInterface`. Once `isPropagationStopped()` returns `true`, the remaining listeners get the `Skipped` status.

The flag is best kept in the payload: it is one object shared by all copies of the event, so a change made by one listener is seen by the next ones.

## What is not caught

The router deliberately lets two errors out under any strategy:

- **an exception in router middleware** (`$events->add()`): it does not belong to any listener;
- **an exception in the error handler itself**: a broken handler is better noticed right away than losing errors silently.
