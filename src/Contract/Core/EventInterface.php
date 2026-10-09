<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Core;

/**
 * Событие: имя-топик, данные и атрибуты.
 *
 * Имя — конкретный топик из сегментов через точку, например `shop.order.42.paid`.
 * Роутер сопоставляет его с шаблонами маршрутов (`order.{orderId}.paid`)
 * и кладёт найденные параметры в атрибуты, как Slim кладёт `{id}` из URL в атрибуты запроса.
 *
 * Событие неизменяемо, как PSR-7 запрос: `withAttribute()` возвращает новый объект.
 * Благодаря этому каждый слушатель получает свою копию со своими параметрами
 * и не может испортить событие для остальных.
 *
 * ```php
 * $event = new Event('shop.order.42.paid', ['amount' => 1500]);
 *
 * $event->getName();                     // 'shop.order.42.paid'
 * $event->getPayload();                  // ['amount' => 1500]
 *
 * $withId = $event->withAttribute('orderId', '42');
 * $withId->getAttribute('orderId');      // '42'
 * $event->getAttribute('orderId');       // null — исходное событие не изменилось
 * ```
 *
 * @template-covariant TPayload
 */
interface EventInterface
{
    /**
     * Имя события — топик из сегментов через точку.
     *
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Данные события: массив, DTO или любой другой объект.
     *
     * @return TPayload
     */
    public function getPayload(): mixed;

    /**
     * Все атрибуты: параметры из шаблона маршрута и то, что добавили middleware.
     *
     * @return array<non-empty-string, mixed>
     */
    public function getAttributes(): array;

    /**
     * Значение атрибута или `$default`, если атрибута нет.
     *
     * @param non-empty-string $name
     */
    public function getAttribute(string $name, mixed $default = null): mixed;

    /**
     * Копия события с добавленным или заменённым атрибутом.
     *
     * @param non-empty-string $name
     */
    public function withAttribute(string $name, mixed $value): static;
}
