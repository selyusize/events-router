<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Override;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Contract\Routing\RouteGroupInterface;
use Selyusize\EventsRouter\Topic\TopicPattern;

/**
 * Группа маршрутов: общий префикс топика и общие middleware.
 *
 * Аналог `RouteCollectorProxy` в Slim. Хранит свои маршруты и вложенные группы
 * в порядке объявления. Middleware группы оборачивают middleware каждого её маршрута,
 * внешняя группа оборачивает вложенную. `add()` можно вызвать после объявления
 * маршрутов — так обычно и пишут:
 *
 * ```php
 * $events->group('order', static function (RouteGroup $group): void {
 *     $group->listen('created', SendConfirmationEmail::class);
 *     $group->listen('{order_id}.paid', MarkOrderPaid::class);
 * })
 *     ->add(SetUserData::class)
 *     ->add(EventLogger::class);   // выполнится первым
 * ```
 */
final class RouteGroup implements RouteGroupInterface
{
    /**
     * @var list<Route|self>
     */
    private array $children = [];

    /**
     * @var list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * @internal группы создаёт роутер или родительская группа
     *
     * @param string $path префиксы всех внешних групп и этой, через точку
     * @param string $prefix префикс этой группы, как передан в `group()`
     */
    public function __construct(
        private readonly Revision $revision,
        private readonly string $path = '',
        private readonly string $prefix = '',
    ) {}

    #[Override]
    public function listen(string $pattern, string $listener): Route
    {
        // Проверяем шаблон и класс сразу, чтобы ошибка указывала на строку с listen().
        // Префикс роутера добавится при сборке таблицы.
        TopicPattern::fromString(self::join($this->path, $pattern));
        RouteAssert::classExists($listener, 'класс слушателя %s не найден');
        RouteAssert::implementsInterface($listener, ListenerInterface::class, 'слушатель %s должен реализовать %2$s');

        /** @var class-string<ListenerInterface> $listener Psalm после Assert считает, что это может быть и объект */
        $route = new Route($pattern, $listener, $this->revision);
        $this->children[] = $route;
        $this->revision->bump();

        return $route;
    }

    #[Override]
    public function group(string $prefix, callable $routes): self
    {
        $group = new self($this->revision, self::join($this->path, $prefix), $prefix);
        $this->children[] = $group;
        $this->revision->bump();

        $routes($group);

        return $group;
    }

    #[Override]
    public function add(MiddlewareInterface|string $middleware): self
    {
        RouteAssert::middleware($middleware);

        $this->middleware[] = $middleware;
        $this->revision->bump();

        return $this;
    }

    /**
     * Префикс группы, как он передан в `group()`.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Middleware этой группы в порядке выполнения: первым идёт добавленный последним.
     * Middleware внешних групп сюда не входят.
     *
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return array_reverse($this->middleware);
    }

    /**
     * Маршруты и вложенные группы в порядке объявления.
     *
     * @return list<Route|self>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Соединить части топика через точку, пропуская пустые.
     */
    public static function join(string ...$parts): string
    {
        return implode('.', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
