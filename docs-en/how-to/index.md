# How-to guides

A guide answers one specific task. Not a feature overview and not a textbook, but a sequence of steps after which the task is solved.

If you have not run the router yet, start with the [tutorial](../tutorial/index.md): the guides assume the basic example already works for you.

| Task | Guide |
| --- | --- |
| listeners need project services | [Plug in a container](container.md) |
| you want to see which events arrived and who handled them | [Set up the log](logging.md) |
| there are many routes, and every request builds them again | [Enable the route cache](cache.md) |
| a listener failed: what now | [Handle listener errors](errors.md) |
| events come from a queue | [Run a worker](workers.md) |
| a Bitrix project, handlers in `init.php` | [Connect Bitrix events](bitrix.md) |
| the event data is an object, not an array | [Pass data as an object](typed-events.md) |
| the code knows only PSR-14 | [Connect via PSR-14](psr14.md) |
| you already know routes in Slim | [Coming from Slim](from-slim.md) |
