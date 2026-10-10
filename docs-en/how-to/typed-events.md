# Pass data as an object

An array payload is convenient until you need to remember its keys. A month later `$payload['amount']` becomes guesswork: is there a `currency`, is it dollars or cents, is `order_id` a string or a number?

The solution is a DTO with a named constructor that builds the event name itself:

```php
--8<-- "typed-events/order-paid.php:dto"
```

The event name is written once, next to the data, not in every `dispatch()` call:

```php
--8<-- "typed-events/order-paid.php:dispatch"
```

The listener gets the same object via `getPayload()`:

```php
--8<-- "typed-events/order-paid.php:listener"
```

```text
--8<-- "typed-events/order-paid.out"
```

What this gives you:

- at the dispatch site you can see which event goes out;
- fields and their types are described in one class, the IDE and Psalm know them;
- `EventInterface` is declared with a `@template TPayload`, so for `Event<OrderPaid>` static analysis knows the payload type.

Why the library does not build the event name from the class itself is in [Explanation](../explanation/design.md#no-magic).

If the DTO implements PSR-14 `StoppableEventInterface`, a listener can stop the dispatch; see [Handle listener errors](errors.md#stop).
