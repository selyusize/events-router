<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Selyusize\EventsRouter\Routing\RouteMatch;
use Selyusize\EventsRouter\Routing\RouteTable;

/**
 * Поиск маршрутов по имени события.
 *
 * В отличие от HTTP-роутера, возвращает **все** подходящие маршруты:
 * у события может быть сколько угодно слушателей.
 */
interface RouteMatcherInterface
{
    /**
     * Все маршруты таблицы, чей шаблон подходит под топик, строго в порядке объявления.
     *
     * @return list<RouteMatch> пустой список, если слушателей нет
     */
    public function match(RouteTable $table, string $topic): array;
}
