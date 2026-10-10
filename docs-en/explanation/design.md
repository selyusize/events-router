# Design decisions

Every library has decisions that look strange at first. Here are such decisions in events-router: what was chosen, why, and what it cost. None of them is accidental.

## One routes file

In Symfony listeners are found through attributes and subscribers scattered across the project. That is convenient while you write the code and inconvenient when you read it. To understand what happens when an order is paid, you have to find every place that mentions the event and put them together in your head.

Here the answer lies in one file. Open it and you see all listeners, their order and middleware. The cost is that the file is maintained by hand. A deliberate trade: a little more typing for a system you can read.

## Order is line order

There are no priorities. Listeners of one event are called in the order their routes are declared.

With priorities, order stops being visible. `priority: 10` in one file and `priority: 20` in another, and you already have to compare numbers from different places, and guess when they are equal. Sooner or later someone sets `priority: 999` "to be sure it runs first".

Line order needs no explanation. Need it earlier? Declare it higher.

## One listener per `listen()`

`listen('order.created', [A::class, B::class])` would look more compact. But then a route would have two listeners with shared middleware, and it would be unclear what happens if the first one fails. One line, one listener, one report entry. Shared middleware goes to the group.

## Static listeners {#static-listeners}

A listener is a class with a static `handle()`, not an object with constructor dependencies.

There are many listeners, and only a few fire on a given event. If each were an object, the router would have to create them through the container, keep an instance cache and decide what to do if creation fails. A static method removes all of this: just call it. Bitrix event handlers work the same way, and the team is used to them.

The cost is real: dependencies are not visible in the signature, the listener takes them from the container itself (`Container::get(...)`). A dependency can be replaced in a test only through the container.

Middleware stays an object: there are fewer of them, and constructor dependencies are more natural for them.

## Errors show up at declaration

The topic pattern, the listener class and the middleware class are checked in `listen()` and `add()`, not when an event arrives.

An event may arrive in a week, at night, in a worker nobody watches. A mistake in a route must surface on the first application start, with a stack trace pointing to the line of the routes file. The cost is that listener classes are loaded by the autoloader when routes are read. For a file with a hundred routes this is unnoticeable.

## The cache is all or nothing

With the route cache, the routes file does not run in production. If routes could also be declared outside `loadRoutes()`, they would work in development and silently disappear in production. So with the cache on, the router forbids such declarations immediately, in development too. Stricter than one would like, but production cannot differ from what you tested.

The cache does not watch files for changes. Checking modification times would cost the same disk access the cache saves, and the routes file can include other files. The rule is simpler: the cache is on in production and reset on deploy, off in development.

## One failure does not break everyone

By default a failed listener does not stop the others. A customer email should not be lost because the bonus service went down. The error does not disappear: it is in the report and in the log. When you need different behaviour, you choose it explicitly, with a strategy.

## A parameter takes the whole segment {#limits}

In Slim you can write `/order-{id}`; here `order-{order_id}` is an error. When a parameter, `*` and `#` take a whole segment, a pattern is split by dots unambiguously, and the route table can be compiled into one regular expression and, in the future, into a search tree. If a segment still needs parsing, a regex in the parameter does it: `{order_ref:order-\d+}`.

## No magic with event names {#no-magic}

The library does not derive the event name from the class name and does not read attributes like `#[Topic('order.{order_id}.paid')]`. The event name is written explicitly, in `new Event(...)` or in a DTO's named constructor.

Magic saves a line but hides the answer to "which event is going out now". It needs reflection, implicit mapping of `{order_id}` to an `$orderId` property, and a second way of doing the same thing. An explicit string is simpler and more honest.

## Bitrix in the payload, not in its own event

A Bitrix event reaches the listener as a normal `Event`, and everything Bitrix-specific (`&$arFields`, the D7 object, cancellation) lies in the payload, in `BitrixEvent`. There is no second `EventInterface` implementation. Listeners, middleware, report and log work for Bitrix events exactly as for your own.

Bitrix calls handlers in two incompatible ways: the old API passes fields by reference, D7 passes an object by value. A closure with a by-reference parameter triggers a PHP warning on D7, and without the reference field changes are lost. The bridge registers the handler as `[$bridge, 'bitrix.main.OnAfterUserAdd']` and takes the call in `__call()`: PHP keeps references from `call_user_func_array()` and does not require them where there are none. One handler for both styles, with no per-event setup. Tests check this behaviour on all supported PHP versions.

## PSR-14 outside, not inside

The router implements the standard `EventDispatcherInterface` through an adapter, but inside it works with its own `EventInterface`. The standard is there to fit into other people's code. The own API is there for topics, parameters, attributes and the report, which is why the router was written in the first place.

## Messages in two languages, one per process

The library's messages are in Russian by default, because the team that wrote it speaks Russian, and in English with `'locale' => 'en'`. The language is one per process, not per router: an event name is validated in `new Event()`, where there is no router to ask. A global setting is a compromise, but the alternative was passing a language into every event.
