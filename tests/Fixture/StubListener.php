<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Contract\Core\ListenerInterface;

/**
 * Слушатель, который ничего не делает: основа для классов в routing-classes.php.
 */
abstract class StubListener implements ListenerInterface
{
    final public static function handle(EventInterface $event): void {}
}
