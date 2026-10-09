<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

/**
 * Найденный маршрут и параметры, извлечённые из топика по его шаблону.
 *
 * У каждого совпадения свои параметры: событие `shop.order.42.paid` совпадёт
 * с `shop.order.{order_id}.paid` (`order_id = '42'`) и с `shop.order.#` (без параметров).
 */
final class RouteMatch
{
    /**
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        private readonly CompiledRoute $route,
        private readonly array $parameters,
    ) {}

    public function getRoute(): CompiledRoute
    {
        return $this->route;
    }

    /**
     * Параметры из шаблона маршрута, например `['order_id' => '42']`.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
