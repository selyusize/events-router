# Performance

An event router does more than a classic dispatcher: it matches patterns, fills in parameters, isolates errors, times each listener and returns a report. All of this costs time. The question is how much, and where that cost is noticeable and where it is not.

Below is a fair comparison with `symfony/event-dispatcher`: where events-router is faster, where it is slower and why.

## How it was measured

The benchmark lives in the repository, `benchmarks/bench.php`, and runs with one command:

```bash
make bench
```

- 50 events with exact names, 4 listeners each: 200 listeners. This is Symfony's home ground: it has no patterns, only exact names.
- Listeners are empty: the dispatcher itself is measured, not the work inside listeners.
- OPcache is on, as in production. JIT is off, as on most production servers.
- Each scenario is warmed up and run five times; the table shows the median.

Results on Apple M4, PHP 8.2, symfony/event-dispatcher 7.4. All numbers are the time of one operation.

### A PHP-FPM request

Each request builds the dispatcher again and dispatches a few events:

| What is measured | symfony/event-dispatcher | events-router | events-router, route cache |
| --- | --- | --- | --- |
| setup: dispatcher with 200 listeners, no dispatches | 31 µs | 643 µs | **2.4 µs** |
| the whole request: setup + 3 dispatches | 38 µs | 1,340 µs | **21 µs** |

The whole request with the cache is 1.8 times faster than Symfony; without the cache it is 35 times slower.

Dispatches inside a request cost differently. For Symfony three dispatches are 38 − 31 ≈ 7 µs, about 2.3 µs each: the first dispatch of an event sorts its listeners. For events-router with the cache it is 21 − 2.4 ≈ 18 µs, about 6 µs each: the first dispatch of an event in a process builds its plan, finding the routes and creating their objects. We win on setup (~28 µs) and lose on each new event in the request (~3.7 µs). So the total is in our favour as long as a request dispatches no more than 7 different events. With more different events, Symfony gets ahead within the request too. A repeated dispatch of the same event in a request costs 1.6 µs.

### A worker

A long-lived process builds the dispatcher once and dispatches the same events again and again:

| What is measured | symfony/event-dispatcher | events-router |
| --- | --- | --- |
| repeated dispatch of an event with 4 listeners | **0.69 µs** | 1.6 µs |

Here Symfony is 2.3 times faster. The route cache does not affect this number: it only speeds up setup.

Your machine will give different numbers. The ratios will be about the same.

## A PHP-FPM request: faster than Symfony

A typical PHP site lives in requests. On every request the application is built again: a dispatcher is created, listeners are registered, a few events are dispatched, everything is thrown away. This build is what costs the most.

On every request Symfony calls `addListener()` 200 times, which is 31 µs. events-router with the [route cache](../how-to/cache.md) does almost nothing and spends 2.4 µs: the route table lies in a PHP file, OPcache serves it from shared memory without copying, and route objects are created only for the ones that matched the event. The routes file does not run, listener classes are not loaded.

Without the cache the picture is reversed: each `listen()` parses the pattern, compiles a regular expression and loads the listener class to check it. That is 0.6 ms to set up 200 routes and as much again to build the table on the first dispatch. Fine for development, where it matters more that a route mistake surfaces immediately. Turn the cache on for production.

## A worker: Symfony is faster by a microsecond

In a long-lived process, a queue worker, the build happens once, and only the cost of repeated dispatches remains. Here Symfony is faster: 0.69 µs against 1.6 µs per event with four listeners.

The difference is about a microsecond, and that is the price of what events-router does and Symfony does not. An approximate breakdown from separate measurements:

| What | Cost |
| --- | --- |
| run time of each listener (`getDuration()` in the report) | ~0.2 µs |
| the dispatch report (`DispatchReport`) | ~0.3 µs |
| event name check and the immutable event | ~0.1 µs |
| route table lookup with patterns | ~0.1 µs |

A microsecond per event means a million events before a second adds up. A real listener goes to the database, sends an email or writes to a queue: hundreds of microseconds and milliseconds. Against that work the difference between dispatchers is not visible.

## What makes it fast

- **Lookup without iteration.** Routes with an exact name are in a hash table, as in Symfony. Routes with patterns are grouped by their literal beginning: for `shop.order.{order_id}.paid` it is `shop.order`, and the regular expression is checked only for routes whose beginning matched the topic.
- **A dispatch plan per topic.** For each topic a plan is built once: which listeners, with which middleware and parameters. A repeated dispatch of the same event takes the ready plan. Building the plan is the main cost of the first dispatch of an event in a process (~6 µs against 1.6 µs for a repeated one).
- **A short path.** If listeners have no middleware and no route parameters, and the event cannot be stopped via PSR-14, each listener costs only a call and a time measurement.
- **A lazy report.** A dispatch records only times and deviations: who failed, who was skipped. `ListenerReport` objects are created when you call `getListeners()`.
- **The cache as an array.** The cached table is data, not code with objects. It needs no parsing, and OPcache keeps it in shared memory for all processes.

## Patterns

Symfony has no topic patterns, so there is nothing to compare here. In events-router a pattern lookup costs one regular expression check per route whose literal beginning matched the topic. The result is remembered: a repeated dispatch of `order.42.paid` does not check the patterns again.
