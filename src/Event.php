<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

use Override;
use Selyusize\EventsRouter\Contract\Core\EventInterface;
use Selyusize\EventsRouter\Exception\InvalidEventName;
use Selyusize\EventsRouter\Locale\Messages;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

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
     * Имена, которые уже прошли проверку: одно и то же событие создаётся много раз,
     * а регулярное выражение на каждое — лишняя работа. Имён с id бесконечно много,
     * поэтому при переполнении список очищается.
     *
     * @var array<string, true>
     */
    private static array $validNames = [];

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
        // Корректное имя проверяется одним выражением: событий много, и рассылка не должна тратить время на четыре проверки.
        // Assert ниже нужен, только чтобы объяснить, что именно не так
        if (isset(self::$validNames[$name])) {
            /** @var non-empty-string $name проверено при первом создании события с этим именем */
            $this->name = $name;

            return;
        }

        if (preg_match('/^[^\s.*#{}]+(?:\.[^\s.*#{}]+)*$/uD', $name) === 1) {
            if (\count(self::$validNames) >= 1024) {
                self::$validNames = [];
            }

            self::$validNames[$name] = true;

            /** @var non-empty-string $name выражение не пропускает пустую строку */
            $this->name = $name;

            return;
        }

        try {
            Assert::stringNotEmpty($name, Messages::translate('имя пустое'));
            Assert::notRegex($name, '/\s/u', Messages::translate('имя содержит пробельные символы'));
            Assert::notRegex($name, '/[*#{}]/', Messages::translate('символы *, #, {, } допустимы только в шаблонах маршрутов, а не в имени события'));
            Assert::notRegex($name, '/^\.|\.\.|\.$/', Messages::translate('пустой сегмент: точка в начале, в конце или две точки подряд'));
        } catch (InvalidArgumentException $error) {
            throw InvalidEventName::because($name, $error->getMessage());
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
