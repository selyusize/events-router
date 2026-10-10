<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Locale;

/**
 * Сообщения библиотеки на выбранном языке.
 *
 * Сообщения пишутся в коде по-русски, русский текст и есть ключ перевода:
 *
 * ```php
 * Assert::stringNotEmpty($pattern, Messages::translate('шаблон пустой'));
 * ```
 *
 * Язык общий на процесс: имя события проверяется в `new Event()`, где роутера нет.
 * Его задаёт ключ конфига `locale` в EventRouterFactory::create().
 * То, что каждому переводу есть пара в ENGLISH, проверяет MessagesTest.
 */
final class Messages
{
    /**
     * Русский текст → английский. Плейсхолдеры sprintf те же и в том же порядке.
     */
    private const ENGLISH = [
        // Исключения
        'Ошибка в маршрутах: %s. См. %s' => 'Route error: %s. See %s',
        'Неверный конфиг роутера: %s. См. %s' => 'Invalid router config: %s. See %s',
        'Некорректное имя события "%s": %s. См. %s' => 'Invalid event name "%s": %s. See %s',
        'Некорректный шаблон топика "%s": %s. См. %s' => 'Invalid topic pattern "%s": %s. See %s',
        'Неизвестно имя события для объекта %s: реализуйте EventInterface или передайте маппер в Psr14EventDispatcher. См. %s' => 'Unknown event name for object %s: implement EventInterface or pass a mapper to Psr14EventDispatcher. See %s',

        // Конфиг
        'неизвестный ключ %s, допустимые: %2$s' => 'unknown key %s, allowed: %2$s',
        'locale должен быть одним из %2$s, передано %s' => 'locale must be one of %2$s, got %s',
        'log_path должен быть непустой строкой, передано %s' => 'log_path must be a non-empty string, got %s',
        'log_dispatch должен быть true или false, передано %s' => 'log_dispatch must be true or false, got %s',
        'route_cache_file должен быть непустой строкой, передано %s' => 'route_cache_file must be a non-empty string, got %s',

        // Имя события и шаблон топика
        'имя пустое' => 'name is empty',
        'имя содержит пробельные символы' => 'name contains whitespace',
        'символы *, #, {, } допустимы только в шаблонах маршрутов, а не в имени события' => 'characters *, #, {, } are allowed only in route patterns, not in an event name',
        'пустой сегмент: точка в начале, в конце или две точки подряд' => 'empty segment: a dot at the start, at the end, or two dots in a row',
        'шаблон пустой' => 'pattern is empty',
        'не закрыта фигурная скобка' => 'unclosed curly brace',
        'лишняя закрывающая фигурная скобка' => 'unexpected closing curly brace',
        'параметр в сегменте %s должен занимать весь сегмент, например order.{order_id}' => 'parameter in segment %s must take the whole segment, e.g. order.{order_id}',
        '* и # в сегменте %s должны занимать весь сегмент, например order.*' => '* and # in segment %s must take the whole segment, e.g. order.*',
        'сегмент %s содержит пробельные символы' => 'segment %s contains whitespace',
        'имя параметра %s должно быть в snake_case: строчные латинские буквы, цифры и _, начинается с буквы, например {order_id}' => 'parameter name %s must be snake_case: lowercase latin letters, digits and _, starting with a letter, e.g. {order_id}',
        'параметр {%s} встречается дважды' => 'parameter {%s} occurs twice',
        'пустое ограничение у параметра {%s:}: уберите двоеточие или добавьте regex' => 'empty constraint in parameter {%s:}: remove the colon or add a regex',
        'ошибка в regex параметра {%s}: %s' => 'invalid regex in parameter {%s}: %s',

        // Маршруты
        'класс слушателя %s не найден' => 'listener class %s not found',
        'слушатель %s должен реализовать %2$s' => 'listener %s must implement %2$s',
        'класс middleware %s не найден' => 'middleware class %s not found',
        'middleware %s должен реализовать %2$s' => 'middleware %s must implement %2$s',
        'с кэшем маршрутов loadRoutes() вызывается один раз: подключите все маршруты из одного файла' => 'with the route cache, loadRoutes() is called once: load all routes from one file',
        'с кэшем маршрутов (route_cache_file) маршруты объявляются только внутри loadRoutes()' => 'with the route cache (route_cache_file), routes are declared only inside loadRoutes()',
        'кэш маршрутов хранит middleware только именем класса, а %s добавлен объектом: передайте в add() имя класса' => 'the route cache stores middleware by class name only, but %s was added as an object: pass the class name to add()',
        'Кэш маршрутов events-router. Удалите файл после изменения маршрутов.' => 'events-router route cache. Delete this file after changing routes.',

        // Лог и предупреждения
        'events-router: %s, слушателей: %d, %.1f мс' => 'events-router: %s, listeners: %d, %.1f ms',
        'events-router: слушатель %s упал на событии %s: %s: %s в %s:%d' => 'events-router: listener %s failed on event %s: %s: %s in %s:%d',
        'events-router: не удалось записать кэш маршрутов в %s' => 'events-router: failed to write the route cache to %s',
        'events-router: не удалось создать папку для лога %s' => 'events-router: failed to create the log directory %s',
        'events-router: не удалось записать лог в %s' => 'events-router: failed to write the log to %s',

        // Bitrix
        'маршрут "%s": EventManager Bitrix подписывается только на конкретное событие, нужен топик %s.<модуль>.<Событие> без *, # и параметров' => 'route "%s": Bitrix EventManager subscribes only to a specific event, the topic must be %s.<module>.<Event> without *, # and parameters',
        'У события %s:%s нет массива полей в первом аргументе. %s' => 'Event %s:%s has no fields array in its first argument. %s',
        'Используйте getArguments().' => 'Use getArguments().',
        'Это событие D7: используйте getD7Event().' => 'This is a D7 event: use getD7Event().',
    ];

    private static LocaleEnum $locale = LocaleEnum::Ru;

    public static function setLocale(LocaleEnum $locale): void
    {
        self::$locale = $locale;
    }

    public static function getLocale(): LocaleEnum
    {
        return self::$locale;
    }

    /**
     * Текст на выбранном языке. Для текста без перевода — сам текст.
     */
    public static function translate(string $text): string
    {
        if (self::$locale === LocaleEnum::Ru) {
            return $text;
        }

        return self::ENGLISH[$text] ?? $text;
    }
}
