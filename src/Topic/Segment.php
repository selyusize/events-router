<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Topic;

/**
 * Один разобранный сегмент шаблона топика.
 *
 * @internal
 */
final class Segment
{
    /**
     * @param non-empty-string      $value     текст литерала или имя параметра
     * @param non-empty-string|null $regex     готовое регулярное выражение для параметра с ограничением
     */
    private function __construct(
        public readonly SegmentType $type,
        public readonly string $value,
        public readonly ?string $regex = null,
    ) {}

    /**
     * @param non-empty-string $value
     */
    public static function literal(string $value): self
    {
        return new self(SegmentType::Literal, $value);
    }

    /**
     * @param non-empty-string      $name
     * @param non-empty-string|null $regex
     */
    public static function parameter(string $name, ?string $regex = null): self
    {
        return new self(SegmentType::Parameter, $name, $regex);
    }

    public static function anySegment(): self
    {
        return new self(SegmentType::AnySegment, '*');
    }

    public static function anySegments(): self
    {
        return new self(SegmentType::AnySegments, '#');
    }

    /**
     * Подходит ли один сегмент топика под этот сегмент шаблона.
     *
     * Для `#` не вызывается: он поглощает несколько сегментов и обрабатывается в TopicPattern.
     */
    public function accepts(string $segment): bool
    {
        if ($segment === '') {
            return false;
        }

        return match ($this->type) {
            SegmentType::Literal => $segment === $this->value,
            SegmentType::Parameter => $this->regex === null || preg_match($this->regex, $segment) === 1,
            SegmentType::AnySegment, SegmentType::AnySegments => true,
        };
    }
}
