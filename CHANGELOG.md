# Changelog

Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/), версии — [SemVer](https://semver.org/lang/ru/). До 1.0 API может меняться в минорных версиях.

## [Unreleased]

### Изменено

- **Несовместимо:** enum называются с суффиксом `Enum`: `ErrorStrategy` → `ErrorStrategyEnum`, `ListenerStatus` → `ListenerStatusEnum`.
- **Несовместимо:** все интерфейсы перенесены в `Contract\`: `Exception\ExceptionInterface` → `Contract\Exception\ExceptionInterface`, `Routing\RouteCollectorInterface` → `Contract\Routing\RouteCollectorInterface`.
- Добавлены `Contract\Routing\RouteGroupInterface` и `RouteInterface`: их реализуют `RouteGroup` и `Route`.
- **Несовместимо:** `$next` в middleware — замыкание, как в Laravel: `MiddlewareInterface::process(EventInterface $event, Closure $next)`, вызов дальше — `$next($event)`. `EventHandlerInterface` удалён.
- **Несовместимо:** маршрут, который возвращает `listen()`, называется `Route` (был `RouteDefinition`); маршрут из таблицы — `CompiledRoute` (был `Route`). Поиск — `RouteTable::match()`, отдельного `RouteMatcher` нет.
- **Несовместимо:** `setErrorHandler()` принимает только объект.
- **Несовместимо:** удалено исключение `UnresolvableHandler`: опечатка в имени класса слушателя или middleware даёт обычную ошибку PHP и попадает в отчёт.
- Шаблон топика компилируется в одно регулярное выражение, сопоставление — один `preg_match`. Внутренние `Segment` и `SegmentTypeEnum` удалены.
- Удалён внутренний `Service\RouteMatcherInterface`: у него была одна реализация.
- **Несовместимо:** удалены неиспользуемые методы: `RouteDefinition::name()`, `Route::getName()`, `Route::getIndex()`, `TopicPattern::matches()` (используйте `match() !== null`), `TopicPattern::isExact()`, `TopicPattern::getParameterNames()`. Имя маршрута вернётся, когда понадобится для асинхронной обработки.

### Исправлено

- Документация: примеры кода на страницах показывались как текст, а не как блоки PHP.

## [0.1.0] — 2026-10-10

Первый релиз.

### Добавлено

- **События.** `EventInterface` и `Event`: имя-топик, payload, иммутабельные атрибуты. Проверка имени — `InvalidEventName`.
- **Шаблоны топиков.** `TopicPattern`: параметры `{order_id}` и `{order_id:\d+}` (имена в snake_case), `*` — один сегмент, `#` — ноль и больше сегментов, как в AMQP. Ошибки синтаксиса — `InvalidTopicPattern` при регистрации маршрута.
- **Маршруты в стиле Slim.** `listen()` — один статичный слушатель на вызов, `group()` с префиксом (в том числе пустым), `add()` для middleware (последний добавленный выполняется первым), `name()`, `setPrefix()`. Слушатели одного события вызываются строго в порядке объявления, приоритетов нет.
- **Поиск и рассылка.** `match()` находит все подходящие маршруты с параметрами. `dispatch()` прогоняет каждого слушателя через его middleware и возвращает `DispatchReport` со статусами `Handled`, `Failed`, `Skipped` и временем. Middleware роутера (`$events->add()`) выполняются один раз на событие.
- **Обработка ошибок.** Ошибка одного слушателя не мешает остальным. `setErrorHandler()` (по умолчанию — `error_log()`, есть PSR-3 обработчик), стратегии `ErrorStrategy::Continue`, `Stop`, `Throw`, остановка рассылки через PSR-14 `StoppableEventInterface`.
- **Контейнер.** `EventRouterFactory::create(?ContainerInterface)`: middleware и обработчик ошибок берутся из PSR-11 контейнера или создаются через `new`. Ошибки — `UnresolvableHandler`.
- **Воркеры.** `run(EventSourceInterface)` рассылает события из источника; `InMemoryEventSource` для тестов.
- **PSR-14.** `Psr14EventDispatcher`: роутер под `Psr\EventDispatcher\EventDispatcherInterface`, объекты без `EventInterface` превращаются в события через явный маппер. Ошибка — `UnmappableEvent`.
- **Документация** на GitHub Pages: руководство с исполняемыми примерами, справочник API из PHPDoc, страница на каждое исключение.

[Unreleased]: https://github.com/selyusize/events-router/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/selyusize/events-router/releases/tag/v0.1.0
