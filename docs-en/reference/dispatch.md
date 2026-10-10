# Dispatch and report

`dispatch()` sends an event to all matching listeners and returns a report.

```php
--8<-- "dispatching/dispatch.php:classes"
```

```php
--8<-- "dispatching/dispatch.php:dispatch"
```

```text
--8<-- "dispatching/dispatch.out"
```

## How a dispatch runs

```text
dispatch(order.42.paid)
  └─ router middleware: $events->add(...)           once per event
       └─ for each route in declaration order:
            group middleware → route middleware → listener
```

1. The router finds all matching routes, like [`match()`](routes.md#match).
2. The event passes through **router middleware**. It runs once per event, even if there are no listeners.
3. Each listener in turn, in route declaration order:
    - gets a **copy** of the event with its route parameters in the attributes (`order_id = '42'`);
    - passes through **its own** middleware: groups first, then the route.
4. A listener error is recorded in the report, then the [strategy](#strategies) applies.
5. If `log_dispatch` is on, the dispatch is written to the [log](../how-to/logging.md).

## Report {#report}

`DispatchReport`:

| Method | Returns |
| --- | --- |
| `getEvent()` | the event passed to `dispatch()` |
| `getListeners()` | a `ListenerReport` for each matching route, in call order |
| `hasListeners()` | whether the event had listeners |
| `hasFailures()`, `getFailures()` | failed listeners |

`ListenerReport`:

| Method | Returns |
| --- | --- |
| `getStatus()` | `ListenerStatusEnum`: see the table below |
| `isFailed()` | the status is `Failed` |
| `getError()` | the exception for `Failed`, otherwise `null` |
| `getRoute()`, `getListener()` | the route that fired and its listener class |
| `getEvent()` | the event the listener received, with route parameters |
| `getDuration()` | listener run time including its middleware, in seconds |

## Statuses

| `ListenerStatusEnum` | What happened |
| --- | --- |
| `Handled` | the listener finished without an exception |
| `Failed` | an exception in the listener or its middleware |
| `Skipped` | the listener was not called: middleware did not call `$next`, the dispatch was stopped via `StoppableEventInterface` or by the `Stop` strategy |

## Error strategies {#strategies}

Set with `$events->setErrorStrategy(...)`.

| `ErrorStrategyEnum` | Error handler | Other listeners | Exception from `dispatch()` |
| --- | --- | --- | --- |
| `Continue`, default | called | called | no |
| `Stop` | called | `Skipped` | no |
| `Throw` | not called | not called | yes, the original one |

An exception from **router middleware** and from **the error handler itself** leaves `dispatch()` under any strategy.

How to use this is in the guide [Handle listener errors](../how-to/errors.md).
