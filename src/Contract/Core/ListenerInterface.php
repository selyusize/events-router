<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Contract\Core;

/**
 * Слушатель события — аналог Action в Slim.
 *
 * Один класс — одна реакция на событие. Зависимости передаются через конструктор,
 * роутер берёт слушателя из PSR-11 контейнера. На одно событие может быть
 * сколько угодно слушателей, каждый вызывается отдельно.
 *
 * ```php
 * final class SendConfirmationEmail implements ListenerInterface
 * {
 *     public function __construct(
 *         private readonly Mailer $mailer,
 *     ) {}
 *
 *     public function handle(EventInterface $event): void
 *     {
 *         $this->mailer->sendOrderConfirmation($event->getAttribute('orderId'));
 *     }
 * }
 * ```
 */
interface ListenerInterface
{
    /**
     * Обработать событие.
     */
    public function handle(EventInterface $event): void;
}
