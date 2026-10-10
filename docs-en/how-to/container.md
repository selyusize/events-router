# Plug in a container

A listener almost always needs something: a mail service, a repository, an HTTP client. Creating these by hand in every listener leads to duplication and chaos. That is what a container is for.

## Without your own container

Nothing to do. The router starts [PHP-DI](https://php-di.org) with autowiring itself: classes with constructor dependencies are created without registration.

```php
--8<-- "container/autowiring.php:classes"
```

```php
--8<-- "container/autowiring.php:example"
```

```text
--8<-- "container/autowiring.out"
```

Two mechanisms are at work here:

- **The listener is static**, so it takes its dependencies itself: `Container::get(Mailer::class)`. `Container` is the library's static facade, `Selyusize\EventsRouter\Container\Container`.
- **Middleware is a regular object.** Put the class name in `add()`, and the container creates it with all its dependencies when an event reaches that middleware.

## With the project's container

If the project already has a container, pass it to the factory:

```php
$events = EventRouterFactory::create($container);   // any Psr\Container\ContainerInterface
```

From then on both middleware and `Container::get()` in listeners work with your container. There will not be two containers.

## Another container

Any container implementing PSR-11 `Psr\Container\ContainerInterface` works. The library needs PHP-DI only as a fallback when you have no container. The router expects one thing from the container: `get(ClassName::class)` returns an object of that class.

| Container | What to do |
| --- | --- |
| PHP-DI | nothing: it creates any class itself |
| Laravel (`Illuminate\Container\Container`) | nothing: it also creates classes itself |
| Symfony DependencyInjection | middleware and services that listeners take via `Container::get()` must be **public** services with the class name as id: Symfony services are private by default |
| a simple "key → object" container | register every middleware that routes reference by class name |

The `Container` facade is one per process. If you create two routers with different containers, `Container::get()` looks into the last one passed. A normal application has one router, so this is not a problem.

## If the container does not create classes itself

PHP-DI creates any class without registration. Other containers cannot always do that. If your container works only with what is registered in it, register the middleware that routes reference by class name too.

Otherwise the container throws when an event reaches such middleware. The listener behind it gets the `Failed` status, the others work as usual.

## If the class does not exist at all

The router catches a typo in a class name right away, in `listen()` and `add()`, not when the event arrives. The exception is [`InvalidRoute`](../errors/invalid-route.md), and the stack trace shows the line of the routes file with the mistake.
