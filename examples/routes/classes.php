<?php

declare(strict_types=1);

namespace {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Классы из файла маршрутов examples/routes/events.php. Роутер проверяет классы
// уже в listen() и add(), поэтому для примера они объявлены пустыми.

namespace App\Events\Listener\User {
    use Selyusize\EventsRouter\Contract\Core\EventInterface;
    use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

    final class CreateBonusAccount implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }

    final class SendWelcomeEmail implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }

    final class RevokeTokens implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }
}

namespace App\Events\Listener\Order {
    use Selyusize\EventsRouter\Contract\Core\EventInterface;
    use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

    final class ReserveStock implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }

    final class SendConfirmationEmail implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }

    final class MarkOrderPaid implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }

    final class AuditPayment implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }

    final class RefundPayment implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }

    final class SendUnpaidReminder implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }
}

namespace App\Events\Listener\Bonuses {
    use Selyusize\EventsRouter\Contract\Core\EventInterface;
    use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

    final class AccrueBonuses implements ListenerInterface
    {
        public static function handle(EventInterface $event): void {}
    }
}

namespace App\Events\Middleware\EventLog {
    use Closure;
    use Selyusize\EventsRouter\Contract\Core\EventInterface;
    use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

    final class EventLogger implements MiddlewareInterface
    {
        public function process(EventInterface $event, Closure $next): void
        {
            $next($event);
        }
    }
}

namespace App\Events\Middleware\Idempotency {
    use Closure;
    use Selyusize\EventsRouter\Contract\Core\EventInterface;
    use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

    final class IdempotencyGuard implements MiddlewareInterface
    {
        public function process(EventInterface $event, Closure $next): void
        {
            $next($event);
        }
    }
}

namespace App\Events\Middleware\DataEnrichment {
    use Closure;
    use Selyusize\EventsRouter\Contract\Core\EventInterface;
    use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;

    final class SetUserData implements MiddlewareInterface
    {
        public function process(EventInterface $event, Closure $next): void
        {
            $next($event);
        }
    }
}
