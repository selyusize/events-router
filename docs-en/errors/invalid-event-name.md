# InvalidEventName

`Selyusize\EventsRouter\Exception\InvalidEventName`

The event name is not a valid topic.

## Why

An event name is a **concrete** topic. The rules:

| Rule | Wrong | Right |
| --- | --- | --- |
| the name is not empty | `''` | `ping` |
| no whitespace | `order paid` | `order.paid` |
| no pattern characters `*`, `#`, `{`, `}` | `order.{order_id}.paid` | `order.42.paid` |
| no empty segments | `.order`, `order.`, `order..paid` | `order.paid` |

The characters `*`, `#`, `{`, `}` are for routes, to describe a *group* of events: `order.{order_id}.paid` matches `order.42.paid`, `order.43.paid` and so on. In the event itself the parameter is already filled in.

## How to fix

- Put the parameter values into the name: `'order.' . $orderId . '.paid'`.
- Replace spaces with a dot, a hyphen or an underscore.
- Remove extra dots.

```php
<?php

// Wrong
new Event('order.{order_id}.paid', ['order_id' => 42]);

// Right
new Event('order.42.paid');
```
