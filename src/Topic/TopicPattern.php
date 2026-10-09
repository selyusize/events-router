<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Topic;

use Selyusize\EventsRouter\Exception\InvalidTopicPattern;

/**
 * Шаблон топика — то, что пишется в маршруте вместо точного имени события.
 *
 * Шаблон состоит из сегментов через точку. Каждый сегмент — одно из:
 *
 * | Сегмент          | Что совпадает                                   | Пример                                   |
 * |------------------|-------------------------------------------------|------------------------------------------|
 * | `order`          | ровно этот текст, с учётом регистра             | `order`                                  |
 * | `{order_id}`      | любой один сегмент, значение — в параметр       | `42` → `order_id = '42'`                 |
 * | `{order_id:\d+}`  | один сегмент, целиком подходящий под regex       | `42`, но не `abc`                        |
 * | `*`              | любой один сегмент                              | `42`, `abc`                              |
 * | `#`              | ноль или больше любых сегментов                 | ничего, `42`, `42.payment.failed`        |
 *
 * Параметр, `*` и `#` занимают сегмент целиком: `order-{id}` и `ord*` — ошибка.
 * Имя параметра — snake_case: строчные латинские буквы, цифры и `_`, начинается с буквы.
 *
 * ```php
 * $pattern = TopicPattern::fromString('order.{order_id:\d+}.#');
 *
 * $pattern->match('order.42.paid');          // ['order_id' => '42']
 * $pattern->match('order.42');               // ['order_id' => '42'] — # совпадает и с нулём сегментов
 * $pattern->match('order.abc.paid');         // null — abc не подходит под \d+
 * $pattern->getParameterNames();             // ['order_id']
 * ```
 */
final class TopicPattern
{
    /**
     * @param non-empty-string   $pattern
     * @param non-empty-list<Segment> $segments
     * @param list<non-empty-string>  $parameterNames
     */
    private function __construct(
        private readonly string $pattern,
        private readonly array $segments,
        private readonly array $parameterNames,
    ) {}

    /**
     * Разобрать шаблон.
     *
     * @throws InvalidTopicPattern если шаблон записан с ошибкой
     */
    public static function fromString(string $pattern): self
    {
        if ($pattern === '') {
            throw InvalidTopicPattern::because($pattern, 'шаблон пустой');
        }

        $segments = array_map(
            static fn (string $raw): Segment => self::parseSegment($pattern, $raw),
            self::split($pattern),
        );

        $parameterNames = [];

        foreach ($segments as $segment) {
            if ($segment->type !== SegmentType::Parameter) {
                continue;
            }

            if (\in_array($segment->value, $parameterNames, true)) {
                throw InvalidTopicPattern::because($pattern, \sprintf('параметр {%s} встречается дважды', $segment->value));
            }

            $parameterNames[] = $segment->value;
        }

        return new self($pattern, $segments, $parameterNames);
    }

    /**
     * Исходная строка шаблона.
     *
     * @return non-empty-string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Имена параметров в порядке появления в шаблоне.
     *
     * @return list<non-empty-string>
     */
    public function getParameterNames(): array
    {
        return $this->parameterNames;
    }

    /**
     * Шаблон без параметров и `*`, `#`, то есть совпадает ровно с одним топиком.
     */
    public function isExact(): bool
    {
        foreach ($this->segments as $segment) {
            if ($segment->type !== SegmentType::Literal) {
                return false;
            }
        }

        return true;
    }

    /**
     * Подходит ли топик под шаблон.
     */
    public function matches(string $topic): bool
    {
        return $this->match($topic) !== null;
    }

    /**
     * Сопоставить топик с шаблоном.
     *
     * @return array<non-empty-string, non-empty-string>|null параметры (пустой массив, если их нет)
     *                                                        или null, если топик не подходит
     */
    public function match(string $topic): ?array
    {
        if ($topic === '') {
            return null;
        }

        return $this->matchFrom(explode('.', $topic), 0, 0);
    }

    /**
     * @param non-empty-list<string> $topic
     *
     * @return array<non-empty-string, non-empty-string>|null
     */
    private function matchFrom(array $topic, int $patternIndex, int $topicIndex): ?array
    {
        $patternCount = \count($this->segments);
        $topicCount = \count($topic);

        for (; $patternIndex < $patternCount; ++$patternIndex) {
            $segment = $this->segments[$patternIndex];

            if ($segment->type === SegmentType::AnySegments) {
                // # поглощает от нуля до всех оставшихся сегментов: пробуем по очереди
                for ($skip = $topicIndex; $skip <= $topicCount; ++$skip) {
                    $rest = $this->matchFrom($topic, $patternIndex + 1, $skip);

                    if ($rest !== null) {
                        return $rest;
                    }
                }

                return null;
            }

            if ($topicIndex >= $topicCount || !$segment->accepts($topic[$topicIndex])) {
                return null;
            }

            if ($segment->type === SegmentType::Parameter) {
                $rest = $this->matchFrom($topic, $patternIndex + 1, $topicIndex + 1);

                if ($rest === null) {
                    return null;
                }

                /** @var non-empty-string $value проверено в Segment::accepts() */
                $value = $topic[$topicIndex];

                return [$segment->value => $value] + $rest;
            }

            ++$topicIndex;
        }

        return $topicIndex === $topicCount ? [] : null;
    }

    /**
     * Разбить шаблон на сегменты по точкам вне фигурных скобок:
     * в regex параметра могут быть и точки, и скобки — `{code:\d{3}}`.
     *
     * @param non-empty-string $pattern
     *
     * @return non-empty-list<string>
     */
    private static function split(string $pattern): array
    {
        $segments = [];
        $current = '';
        $depth = 0;
        $length = \strlen($pattern);

        for ($i = 0; $i < $length; ++$i) {
            $char = $pattern[$i];

            if ($char === '\\' && $depth > 0 && $i + 1 < $length) {
                $current .= $char . $pattern[++$i];

                continue;
            }

            if ($char === '{') {
                ++$depth;
            } elseif ($char === '}') {
                if ($depth === 0) {
                    throw InvalidTopicPattern::because($pattern, 'лишняя закрывающая фигурная скобка');
                }

                --$depth;
            } elseif ($char === '.' && $depth === 0) {
                $segments[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($depth > 0) {
            throw InvalidTopicPattern::because($pattern, 'не закрыта фигурная скобка');
        }

        $segments[] = $current;

        return $segments;
    }

    /**
     * @param non-empty-string $pattern
     */
    private static function parseSegment(string $pattern, string $raw): Segment
    {
        if ($raw === '') {
            throw InvalidTopicPattern::because($pattern, 'пустой сегмент: точка в начале, в конце или две точки подряд');
        }

        if ($raw === '*') {
            return Segment::anySegment();
        }

        if ($raw === '#') {
            return Segment::anySegments();
        }

        if ($raw[0] === '{' && self::closingBraceIsLast($raw)) {
            return self::parseParameter($pattern, substr($raw, 1, -1));
        }

        if (str_contains($raw, '{')) {
            throw InvalidTopicPattern::because($pattern, \sprintf('параметр в сегменте "%s" должен занимать весь сегмент, например order.{order_id}', $raw));
        }

        if (strpbrk($raw, '*#') !== false) {
            throw InvalidTopicPattern::because($pattern, \sprintf('* и # в сегменте "%s" должны занимать весь сегмент, например order.*', $raw));
        }

        if (preg_match('/\s/u', $raw) === 1) {
            throw InvalidTopicPattern::because($pattern, \sprintf('сегмент "%s" содержит пробельные символы', $raw));
        }

        return Segment::literal($raw);
    }

    /**
     * @param non-empty-string $pattern
     */
    private static function parseParameter(string $pattern, string $definition): Segment
    {
        $colon = strpos($definition, ':');
        $name = $colon === false ? $definition : substr($definition, 0, $colon);
        $constraint = $colon === false ? null : substr($definition, $colon + 1);

        if ($name === '' || preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw InvalidTopicPattern::because($pattern, \sprintf('имя параметра "{%s}" должно быть в snake_case: строчные латинские буквы, цифры и _, начинается с буквы, например {order_id}', $definition));
        }

        if ($constraint === null) {
            return Segment::parameter($name);
        }

        if ($constraint === '') {
            throw InvalidTopicPattern::because($pattern, \sprintf('пустое ограничение у параметра {%s:}: уберите двоеточие или добавьте regex', $name));
        }

        // Фигурные скобки как разделители regex: внутри они сбалансированы, это проверил split()
        $regex = '{^(?:' . $constraint . ')$}u';
        $error = self::regexError($regex);

        if ($error !== null) {
            throw InvalidTopicPattern::because($pattern, \sprintf('ошибка в regex параметра {%s}: %s', $name, $error));
        }

        return Segment::parameter($name, $regex);
    }

    /**
     * Сегмент вида `{...}`, где закрывающая скобка, парная первой, — последний символ.
     */
    private static function closingBraceIsLast(string $raw): bool
    {
        $depth = 0;
        $length = \strlen($raw);

        for ($i = 0; $i < $length; ++$i) {
            if ($raw[$i] === '\\') {
                ++$i;

                continue;
            }

            if ($raw[$i] === '{') {
                ++$depth;
            } elseif ($raw[$i] === '}' && --$depth === 0) {
                return $i === $length - 1;
            }
        }

        return false;
    }

    /**
     * @param non-empty-string $regex
     */
    private static function regexError(string $regex): ?string
    {
        $error = null;

        set_error_handler(static function (int $_, string $message) use (&$error): bool {
            $error = preg_replace('/^preg_match\(\): /', '', $message);

            return true;
        });

        try {
            $result = preg_match($regex, '');
        } finally {
            restore_error_handler();
        }

        return $result === false ? ($error ?? preg_last_error_msg()) : null;
    }
}
