# Topic patterns

## Topic

A topic is an event name made of segments separated by dots. It is the URL of events: the path `/order/42/paid` ↔ the topic `order.42.paid`.

```text
shop.order.42.paid
│    │     │  └─ what happened
│    │     └──── which order exactly
│    └────────── which entity
└─────────────── which system
```

Rules for an event name: non-empty segments, no whitespace, no `*`, `#`, `{`, `}` characters, case-sensitive. Breaking them gives [`InvalidEventName`](../errors/invalid-event-name.md).

## Pattern segments

A route has a pattern, not an exact name: it describes a whole group of events.

| Segment | What matches | Example |
| --- | --- | --- |
| `order` | exactly this text, case-sensitive | `order` |
| `{order_id}` | any one segment, its value goes into the parameter | `42` → `order_id = '42'` |
| `{order_id:\d+}` | one segment that matches the regular expression as a whole | `42`, but not `abc` and not `42abc` |
| `*` | any one segment | `42`, `abc` |
| `#` | **zero** or more segments of any kind | nothing, `42`, `42.payment.failed` |

```php
--8<-- "topics/matching.php:example"
```

```text
--8<-- "topics/matching.out"
```

## Parameters

| Rule | Example |
| --- | --- |
| the name is snake_case: lowercase latin letters, digits and `_`, starting with a letter | `{order_id}`, `{v2}`; not `{orderId}`, not `{_id}` |
| the constraint is a regex after a colon, it matches the **whole** segment, no `^` and `$` needed | `{order_id:\d+}`, `{code:\d{3}}` |
| Unicode in a constraint is supported | `{name:\p{L}+}` |
| one parameter once per pattern | `user.{id}.order.{id}` is an error |
| an empty segment does not match | `order..paid` does not fit `order.{order_id}.paid` |

Alternation in a constraint works too: `{status:paid|cancelled}`.

A parameter value is a string. The listener gets it via `$event->getAttribute('order_id')`.

The library does not restrict literal segments: Bitrix events are called `OnAfterUserAdd`. For your own events use snake_case: `order.unpaid_reminder`.

## `*` and `#`

| Pattern | Matches | Does not match |
| --- | --- | --- |
| `order.*.cancelled` | `order.42.cancelled` | `order.cancelled`, `order.42.items.cancelled` |
| `order.#` | `order`, `order.created`, `order.42.payment.failed` | `shop.order` |
| `order.#.failed` | `order.failed`, `order.42.payment.failed` | `order.42.paid` |
| `#` | any event | — |

`#` works as in AMQP: it can stand anywhere and match zero segments.

## Limits {#limits}

A parameter, `*` and `#` take the **whole segment**. You cannot write `order-{order_id}` or `ord*`. A segment that needs parsing is caught with a regex parameter: `{order_ref:order-\d+}`.

A mistake in a pattern shows up right when the route is declared, not when an event arrives:

```php
--8<-- "topics/invalid-pattern.php:example"
```

```text
--8<-- "topics/invalid-pattern.out"
```

All cases are on the [`InvalidTopicPattern`](../errors/invalid-topic-pattern.md) page.
