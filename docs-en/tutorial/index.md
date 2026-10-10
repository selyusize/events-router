# Your first router

In ten minutes you will install the library, write a listener, describe a route and send your first event. At the end you will see who received it and how it went.

No theory along the way. Only steps, each with a visible result. Why things are built this way is covered later, in [Explanation](../explanation/index.md).

You will need PHP 8.1 or newer and Composer.

## Step 1. Install the library

```bash
composer require selyusize/events-router
```

Check that everything is in place:

```php
--8<-- "getting-started/check-installation.php:example"
```

You should get:

```text
--8<-- "getting-started/check-installation.out"
```

## Step 2. Write a listener

A listener is a reaction to an event. One class, one static `handle()` method:

```php
--8<-- "getting-started/quick-start.php:listener"
```

`$event->getAttribute('order_id')` is the order number. You will see where it comes from in the next step.

## Step 3. Describe a route

Routes live in a separate file, like HTTP routes in Slim. Create `events.php`:

```php
--8<-- "getting-started/events.php:example"
```

This route catches every event of the form `shop.order.<anything>.paid`. Whatever stands in place of `{order_id}` reaches the listener as the `order_id` attribute.

## Step 4. Send an event

Create the router, load the routes and dispatch an event:

```php
--8<-- "getting-started/quick-start.php:dispatch"
```

`'locale' => 'en'` switches the library's exceptions and log messages to English. Without it they are in Russian.

Result:

```text
--8<-- "getting-started/quick-start.out"
```

The router found the route, took `42` out of the event name, called the listener and returned a report. `hasFailures()` says that all listeners finished without exceptions.

## Step 5. See what happened

The report is good in code, but in a running application you want history. Turn on the dispatch log: it records every event and every listener called:

```php
--8<-- "logging/dispatch-log.php:example"
```

Two entries appear in the log. The first is about the dispatch, the second about the failed listener:

```text
--8<-- "logging/dispatch-log.out"
```

The second listener failed, but the first one did its job. One listener's error does not break the others: it goes to the report and to the log, and the dispatch carries on.

## What next

The router works. Next, depending on your task:

- more routes, groups and middleware: [Routes](../reference/routes.md);
- what you can write in a topic pattern: [Topic patterns](../reference/topics.md);
- dependencies for listeners: [Plug in a container](../how-to/container.md);
- where and how to write the log: [Set up the log](../how-to/logging.md);
- what to do with listener errors: [Handle listener errors](../how-to/errors.md).
