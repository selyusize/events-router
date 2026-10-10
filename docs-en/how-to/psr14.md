# Connect via PSR-14

A third-party library or legacy code knows only the standard `Psr\EventDispatcher\EventDispatcherInterface`. There is no need to rewrite it for the router: `Psr14EventDispatcher` is the same router behind the standard interface.

```php
--8<-- "psr14/psr14.php:example"
```

```text
--8<-- "psr14/psr14.out"
```

## How an object becomes an event

| What is passed to `dispatch()` | What the router gets |
| --- | --- |
| an object implementing `EventInterface` (for example, `Event`) | the same object, as is |
| any other object | `new Event(mapper($object), $object)`: the name comes from the mapper given to the constructor, the object becomes the payload |
| any other object, and there is no mapper | the [`UnmappableEvent`](../errors/unmappable-event.md) exception |

The event name is not guessed from the class name. You set the rule explicitly, in one place.

## What the adapter guarantees

- `dispatch()` returns the same object it received, as PSR-14 requires.
- If the object implements `StoppableEventInterface`, a listener can stop the dispatch.
- Listener errors are handled the same way as in `EventRouter::dispatch()`: the same handler, the same strategy, the same log.

PSR-14 does not return a dispatch report. If you need it, call the router directly.
