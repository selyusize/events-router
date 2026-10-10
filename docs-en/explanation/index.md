# Explanation

There are no step-by-step instructions here. This section is about what stands behind the API: which concepts the library is built from and why the decisions were made this way.

You do not have to read it to use the router. But if something in the API seems odd (why there are no priorities, why the listener is static, why an error surfaces already in `listen()`), the answer is here.

- [Core concepts](concepts.md): event, topic, listener, middleware, route and how they connect.
- [Design decisions](design.md): the decisions that set events-router apart from the usual dispatchers, and their cost.
- [Performance](performance.md): a comparison with symfony/event-dispatcher, where it is faster, where it is slower and why.
