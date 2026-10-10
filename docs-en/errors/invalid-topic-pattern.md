# InvalidTopicPattern

`Selyusize\EventsRouter\Exception\InvalidTopicPattern`

A topic pattern in a route has a mistake. The exception occurs when the route is registered, so a mistake in the routes file is visible right at application start.

The pattern syntax is in [Topic patterns](../reference/topics.md).

## Causes and fixes

| Message | Wrong | Right |
| --- | --- | --- |
| pattern is empty | `''` | `order.paid` |
| empty segment | `.order`, `order.`, `order..paid` | `order.paid` |
| contains whitespace | `order paid` | `order.paid` |
| unclosed curly brace | `order.{order_id` | `order.{order_id}` |
| unexpected closing curly brace | `order.order_id}` | `order.{order_id}` |
| parameter must take the whole segment | `order-{order_id}` | `order.{order_id}` or `{order_ref:order-\d+}` |
| `*` and `#` must take the whole segment | `ord*.paid` | `*.paid` |
| parameter name must be snake_case | `{orderId}`, `{Id}`, `{_id}`, `{1id}`, `{order-id}`, `{статус}` | `{order_id}`, `{id}`, `{id1}`, `{status}` |
| empty constraint | `{id:}` | `{id}` or `{id:\d+}` |
| invalid regex in parameter | `{id:(\d+}` | `{id:(\d+)}` |
| parameter occurs twice | `user.{id}.order.{id}` | `user.{user_id}.order.{order_id}` |

## Regular expressions

A parameter constraint is a PCRE regular expression. It is checked against the whole segment, so `^` and `$` are not needed. A compilation error of the expression is shown in the message as is, for example `missing closing parenthesis`.

Curly braces inside a constraint must be balanced: `{code:\d{3}}` is correct.
