<?php

declare(strict_types=1);

// Пустые слушатели и middleware для тестов маршрутов: роутер проверяет классы в listen() и add(),
// а тестам маршрутов важны только имена.

namespace Fixture\Listener {
    use Selyusize\EventsRouter\Tests\Fixture\StubListener;

    final class Any extends StubListener {}
    final class First extends StubListener {}
    final class Second extends StubListener {}
    final class Third extends StubListener {}
    final class Fourth extends StubListener {}
    final class MarkOrderPaid extends StubListener {}
    final class NotMatching extends StubListener {}
}

namespace Fixture\Middleware {
    use Selyusize\EventsRouter\Tests\Fixture\StubMiddleware;

    final class First extends StubMiddleware {}
    final class Second extends StubMiddleware {}
    final class Late extends StubMiddleware {}
    final class Outer extends StubMiddleware {}
    final class OuterFirst extends StubMiddleware {}
    final class OuterSecond extends StubMiddleware {}
    final class InnerFirst extends StubMiddleware {}
    final class InnerSecond extends StubMiddleware {}
    final class RouteFirst extends StubMiddleware {}
    final class RouteSecond extends StubMiddleware {}
}

namespace Fixture\Listener\User {
    use Selyusize\EventsRouter\Tests\Fixture\StubListener;

    final class CreateBonusAccount extends StubListener {}
    final class SendWelcomeEmail extends StubListener {}
    final class RevokeTokens extends StubListener {}
}

namespace Fixture\Listener\Order {
    use Selyusize\EventsRouter\Tests\Fixture\StubListener;

    final class ReserveStock extends StubListener {}
    final class SendConfirmationEmail extends StubListener {}
    final class MarkOrderPaid extends StubListener {}
    final class AuditPayment extends StubListener {}
    final class RefundPayment extends StubListener {}
    final class SendUnpaidReminder extends StubListener {}
}

namespace Fixture\Listener\Bonuses {
    use Selyusize\EventsRouter\Tests\Fixture\StubListener;

    final class AccrueBonuses extends StubListener {}
}

namespace Fixture\Middleware\EventLog {
    use Selyusize\EventsRouter\Tests\Fixture\StubMiddleware;

    final class EventLogger extends StubMiddleware {}
}

namespace Fixture\Middleware\Idempotency {
    use Selyusize\EventsRouter\Tests\Fixture\StubMiddleware;

    final class IdempotencyGuard extends StubMiddleware {}
}

namespace Fixture\Middleware\DataEnrichment {
    use Selyusize\EventsRouter\Tests\Fixture\StubMiddleware;

    final class SetUserData extends StubMiddleware {}
}
