<?php

declare(strict_types=1);

// --8<-- [start:example]
use App\Events\Listener;
use App\Events\Middleware;
use Selyusize\EventsRouter\EventRouter;
use Selyusize\EventsRouter\Routing\RouteGroup;

return static function (EventRouter $events): void {
    $events->setPrefix('shop');

    // =================================== Пользователи ===================================

    $events->group('user', static function (RouteGroup $group): void {
        $group->listen('registered', Listener\User\CreateBonusAccount::class);
        $group->listen('registered', Listener\User\SendWelcomeEmail::class);
        $group->listen('{user_id}.deactivated', Listener\User\RevokeTokens::class);
    })
        ->add(Middleware\EventLog\EventLogger::class);

    // =================================== Заказы ===================================

    $events->group('', static function (RouteGroup $group): void {
        $group->group('order', static function (RouteGroup $order): void {
            $order->listen('created', Listener\Order\ReserveStock::class);
            $order->listen('created', Listener\Order\SendConfirmationEmail::class);

            $order->group('{order_id}', static function (RouteGroup $one): void {
                $one->listen('paid', Listener\Order\MarkOrderPaid::class);
                $one->listen('paid', Listener\Bonuses\AccrueBonuses::class);
                $one->listen('payment.#', Listener\Order\AuditPayment::class);
            })
                ->add(Middleware\Idempotency\IdempotencyGuard::class);

            $order->listen('*.cancelled', Listener\Order\RefundPayment::class);
        })
            ->add(Middleware\DataEnrichment\SetUserData::class);

        // тот же префикс 'order', но другой набор middleware
        $group->group('order', static function (RouteGroup $order): void {
            $order->listen('{order_id}.unpaid_reminder', Listener\Order\SendUnpaidReminder::class);
        });
    })
        ->add(Middleware\EventLog\EventLogger::class);
};
// --8<-- [end:example]
