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
 * | `{order_id}`     | любой один сегмент, значение — в параметр       | `42` → `order_id = '42'`                 |
 * | `{order_id:\d+}` | один сегмент, целиком подходящий под regex      | `42`, но не `abc`                        |
 * | `*`              | любой один сегмент                              | `42`, `abc`                              |
 * | `#`              | ноль или больше любых сегментов                 | ничего, `42`, `42.payment.failed`        |
 *
 * Параметр, `*` и `#` занимают сегмент целиком: `order-{id}` и `ord*` — ошибка.
 * Имя параметра — snake_case: строчные латинские буквы, цифры и `_`, начинается с буквы.
 *
 * Шаблон компилируется в одно регулярное выражение, как маршруты в FastRoute и Symfony Routing:
 * `order.{order_id:\d+}.#` → `^\.order\.(?=(?:\d+)(?:\.|$))(?P<order_id>[^.]+)(?:\.[^.]+)*$`.
 *
 * ```php
 * $pattern = TopicPattern::fromString('order.{order_id:\d+}.#');
 *
 * $pattern->match('order.42.paid');          // ['order_id' => '42']
 * $pattern->match('order.42');               // ['order_id' => '42'] — # совпадает и с нулём сегментов
 * $pattern->match('order.abc.paid');         // null — abc не подходит под \d+
 * ```
 */
final class TopicPattern
{
    /**
     * Сбалансированные фигурные скобки, с экранированием и вложенностью: `{code:\d{3}}`.
     */
    private const BRACES = '(?<braces>\{(?:[^{}\\\]++|\\\.|(?&braces))*\})';

    /**
     * @param non-empty-string $pattern
     * @param non-empty-string $regex
     */
    private function __construct(
        private readonly string $pattern,
        private readonly string $regex,
    ) {}

    /**
     * Разобрать шаблон и скомпилировать его в регулярное выражение.
     *
     * @throws InvalidTopicPattern если шаблон записан с ошибкой
     */
    public static function fromString(string $pattern): self
    {
        if ($pattern === '') {
            throw InvalidTopicPattern::because($pattern, 'шаблон пустой');
        }

        if (preg_match('/^(?:[^{}]++|' . self::BRACES . ')*+$/', $pattern) !== 1) {
            throw InvalidTopicPattern::because($pattern, substr_count($pattern, '{') > substr_count($pattern, '}')
                ? 'не закрыта фигурная скобка'
                : 'лишняя закрывающая фигурная скобка');
        }

        $regex = '';
        $parameters = [];

        // Точки внутри скобок — часть regex параметра, а не разделитель сегментов
        foreach (preg_split('/' . self::BRACES . '(*SKIP)(*FAIL)|\./', $pattern) ?: [] as $segment) {
            if ($segment === '') {
                throw InvalidTopicPattern::because($pattern, 'пустой сегмент: точка в начале, в конце или две точки подряд');
            }

            if ($segment === '#') {
                $regex .= '(?:\.[^.]+)*';

                continue;
            }

            if ($segment === '*') {
                $regex .= '\.[^.]+';

                continue;
            }

            if (preg_match('/^' . self::BRACES . '$/', $segment) !== 1) {
                if (str_contains($segment, '{')) {
                    throw InvalidTopicPattern::because($pattern, \sprintf('параметр в сегменте "%s" должен занимать весь сегмент, например order.{order_id}', $segment));
                }

                if (strpbrk($segment, '*#') !== false) {
                    throw InvalidTopicPattern::because($pattern, \sprintf('* и # в сегменте "%s" должны занимать весь сегмент, например order.*', $segment));
                }

                if (preg_match('/\s/u', $segment) === 1) {
                    throw InvalidTopicPattern::because($pattern, \sprintf('сегмент "%s" содержит пробельные символы', $segment));
                }

                $regex .= '\.' . preg_quote($segment);

                continue;
            }

            $definition = substr($segment, 1, -1);
            $colon = strpos($definition, ':');
            $name = $colon === false ? $definition : substr($definition, 0, $colon);
            $constraint = $colon === false ? null : substr($definition, $colon + 1);

            if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                throw InvalidTopicPattern::because($pattern, \sprintf('имя параметра "{%s}" должно быть в snake_case: строчные латинские буквы, цифры и _, начинается с буквы, например {order_id}', $definition));
            }

            if (\in_array($name, $parameters, true)) {
                throw InvalidTopicPattern::because($pattern, \sprintf('параметр {%s} встречается дважды', $name));
            }

            if ($constraint === '') {
                throw InvalidTopicPattern::because($pattern, \sprintf('пустое ограничение у параметра {%s:}: уберите двоеточие или добавьте regex', $name));
            }

            // Ограничение проверяется на весь сегмент: от точки до точки или конца топика
            $check = $constraint === null ? '' : '(?=(?:' . $constraint . ')(?:\.|$))';

            if ($check !== '' && @preg_match('{' . $check . '}u', '') === false) {
                throw InvalidTopicPattern::because($pattern, \sprintf(
                    'ошибка в regex параметра {%s}: %s',
                    $name,
                    (string)preg_replace('/^preg_match\(\): /', '', error_get_last()['message'] ?? preg_last_error_msg()),
                ));
            }

            $regex .= '\.' . $check . '(?P<' . $name . '>[^.]+)';
            $parameters[] = $name;
        }

        // Фигурные скобки как разделители: внутри регулярного выражения они сбалансированы
        return new self($pattern, '{^' . $regex . '$}u');
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
     * Сопоставить топик с шаблоном.
     *
     * @return array<non-empty-string, non-empty-string>|null параметры (пустой массив, если их нет)
     *                                                        или null, если топик не подходит
     */
    public function match(string $topic): ?array
    {
        // Точка в начале: каждый сегмент шаблона начинается с точки, так # в начале и «ноль сегментов» работают одинаково
        if (preg_match($this->regex, '.' . $topic, $matches) !== 1) {
            return null;
        }

        /** @var array<non-empty-string, non-empty-string> именованные группы непустые: [^.]+ */
        return array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY);
    }
}
