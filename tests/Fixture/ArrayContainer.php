<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Fixture;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Минимальный PSR-11 контейнер для тестов.
 */
final class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(
        private readonly array $entries,
    ) {}

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class($id) extends RuntimeException implements NotFoundExceptionInterface {};
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->entries);
    }
}
