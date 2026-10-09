<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

final class RecordingListener implements ListenerInterface
{
    public static function handle(EventInterface $event): void
    {
        Journal::write('RecordingListener ' . $event->getName());
    }
}
