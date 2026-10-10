# Changelog

Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/), версии — [SemVer](https://semver.org/lang/ru/). До 1.0 API может меняться в минорных версиях.

## [Unreleased]

## [0.3.0] — 2026-10-10

### Добавлено

- **Контейнер.** Ядро зависит от `php-di/php-di` 7. Статический фасад `Container\Container` (`get()`, `getInstance()`, `set()`) — откуда слушатели берут зависимости. `EventRouterFactory::create($container)` подключает к нему контейнер проекта, без контейнера работает PHP-DI с автосвязыванием.
- **Лог в файл.** `Service\Log\FileLogger` (PSR-3): путь — шаблон с подстановками `{date}` и `{level}`, папки создаются сами. Путь задаётся конфигом фабрики: `EventRouterFactory::create($container, ['log_path' => '…/{level}/{date}.log'])`. `EventRouter::setLogger()` подключает свой логгер вместо файла.
- **Лог рассылки.** Ключ конфига `log_dispatch`: на каждое событие одна запись уровня `info` — имя, число слушателей, время, и по каждому слушателю статус, время, атрибуты и ошибка. Событие без слушателей тоже пишется. Payload не пишется.
- **Конфиг фабрики.** `EventRouterFactory::create($container, array $config)` с ключами `log_path` и `log_dispatch`. Неизвестный ключ или значение не того типа — исключение `InvalidConfig`.
- Исключение `InvalidRoute`: класс слушателя или middleware в маршруте не найден или не реализует нужный интерфейс.

### Изменено

- **Несовместимо:** ошибки слушателей по умолчанию пишутся не в `error_log()`, а в лог роутера (`PsrLoggerErrorHandler` + `FileLogger`). `PhpErrorLogHandler` удалён. `psr/log` перешёл из `suggest` в обязательные зависимости.
- **Несовместимо:** классы проверяются при объявлении маршрута: `listen()` и `add()` бросают `InvalidRoute`. Раньше такой слушатель падал только при рассылке со статусом `Failed`.
- **Несовместимо:** middleware, указанный именем класса, всегда берётся из контейнера. Ветки `new` без аргументов больше нет: с PSR-11 контейнером без автосвязывания middleware нужно зарегистрировать в нём. Middleware с зависимостями в конструкторе теперь создаются без регистрации (PHP-DI по умолчанию).
- Проверки аргументов (`Event`, `TopicPattern`, маршруты) переписаны на `webmozart/assert`. Исключения и тексты ошибок прежние, кроме имени параметра в ошибке snake_case: `имя параметра "orderId"` вместо `"{orderId}"`.
- Документация разложена по [Diátaxis](https://diataxis.fr/): «Обучение», «Рецепты», «Справочник», «Как это устроено». Новые страницы: «Подключить контейнер», «Настроить лог», «Конфигурация», «Почему так». Старые адреса `guide/*` больше не работают.

## [0.2.0] — 2026-10-10

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

[Unreleased]: https://github.com/selyusize/events-router/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/selyusize/events-router/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/selyusize/events-router/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/selyusize/events-router/releases/tag/v0.1.0
