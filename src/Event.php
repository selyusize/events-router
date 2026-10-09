<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

use Override;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Exception\InvalidEventName;

/**
 * Событие со строковым именем — когда отдельный класс события не нужен.
 *
 * Имя — конкретный топик: непустые сегменты через точку, без пробелов
 * и без символов шаблонов `*`, `#`, `{`, `}`. Регистр сохраняется:
 * `bitrix.main.OnAfterUserAdd` и `bitrix.main.onafteruseradd` — разные события.
 *
 * Атрибуты неизменяемы: `withAttribute()` возвращает копию. Payload не копируется:
 * если это объект, все копии события ссылаются на один и тот же объект.
 *
 * ```php
 * $event = new Event('shop.order.42.paid', ['amount' => 1500]);
 * ```
 *
 * @template-covariant TPayload
 *
 * @implements EventInterface<TPayload>
 */
final class Event implements EventInterface
{
    /**
     * @var non-empty-string
     */
    private readonly string $name;

    /**
     * @param string $name топик из сегментов через точку, например `shop.order.42.paid`
     * @param TPayload $payload данные события
     * @param array<non-empty-string, mixed> $attributes начальные атрибуты
     *
     * @throws InvalidEventName если имя не является корректным топиком
     */
    public function __construct(
        string $name,
        private readonly mixed $payload = null,
        private array $attributes = [],
    ) {
        if ($name === '') {
            throw InvalidEventName::because($name, 'имя пустое');
        }

        if (preg_match('/\s/u', $name) === 1) {
            throw InvalidEventName::because($name, 'имя содержит пробельные символы');
        }

        if (strpbrk($name, '*#{}') !== false) {
            throw InvalidEventName::because($name, 'символы *, #, {, } допустимы только в шаблонах маршрутов, а не в имени события');
        }

        if (\in_array('', explode('.', $name), true)) {
            throw InvalidEventName::because($name, 'пустой сегмент: точка в начале, в конце или две точки подряд');
        }

        $this->name = $name;
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function getPayload(): mixed
    {
        return $this->payload;
    }

    #[Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    #[Override]
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    #[Override]
    public function withAttribute(string $name, mixed $value): static
    {
        $copy = clone $this;
        $copy->attributes[$name] = $value;

        return $copy;
    }
}
