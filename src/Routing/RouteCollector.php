<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Exception\InvalidTopicPattern;
use Selyusize\EventsRouter\Topic\TopicPattern;

/**
 * Хранилище маршрутов роутера: общий префикс и все маршруты в порядке объявления.
 *
 * @internal используется EventRouter и RouteGroup
 */
final class RouteCollector
{
    private string $prefix = '';

    /**
     * @var list<Route>
     */
    private array $routes = [];

    /**
     * Соединить части топика через точку, пропуская пустые.
     */
    public static function join(string ...$parts): string
    {
        return implode('.', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @param class-string<ListenerInterface>|ListenerInterface $listener
     *
     * @throws InvalidTopicPattern
     */
    public function addRoute(string $path, ListenerInterface|string $listener, ?RouteGroup $group): Route
    {
        $route = new Route($this->compile($path), $path, $listener, $group, \count($this->routes));
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Сменить префикс и пересобрать шаблоны всех маршрутов.
     *
     * Сначала проверяются все шаблоны, и только потом что-то меняется:
     * при ошибке роутер остаётся в прежнем состоянии.
     *
     * @throws InvalidTopicPattern
     */
    public function setPrefix(string $prefix): void
    {
        $patterns = array_map(fn (Route $route): TopicPattern => $this->compile($route->getPath(), $prefix), $this->routes);

        $this->prefix = $prefix;

        foreach ($this->routes as $i => $route) {
            $route->setPattern($patterns[$i]);
        }
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @throws InvalidTopicPattern
     */
    private function compile(string $path, ?string $prefix = null): TopicPattern
    {
        return TopicPattern::fromString(self::join($prefix ?? $this->prefix, $path));
    }
}
