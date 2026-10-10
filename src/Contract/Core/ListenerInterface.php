<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Core;

/**
 * Слушатель события — аналог Action в Slim.
 *
 * Один класс — одна реакция на событие. Метод статичный: роутер вызывает
 * `Listener::handle($event)` и не создаёт объектов. Зависимости слушатель
 * берёт сам из контейнера роутера (`Container\Container`). На одно событие может быть сколько угодно
 * слушателей, каждый вызывается отдельно.
 *
 * ```php
 * final class SendConfirmationEmail implements ListenerInterface
 * {
 *     public static function handle(EventInterface $event): void
 *     {
 *         Container::get(Mailer::class)->sendOrderConfirmation($event->getAttribute('order_id'));
 *     }
 * }
 * ```
 */
interface ListenerInterface
{
    /**
     * Обработать событие.
     */
    public static function handle(EventInterface $event): void;
}
