<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter;

/**
 * Ссылки на сайт документации.
 *
 * Каждое исключение библиотеки добавляет в текст ссылку на страницу
 * `errors/<slug>` с объяснением причины и способом исправления.
 * Наличие страницы для каждого исключения проверяет ArchitectureTest.
 *
 * ```php
 * throw new InvalidTopicPattern(sprintf(
 *     'Не закрыта фигурная скобка в шаблоне "%s". См. %s',
 *     $pattern,
 *     Documentation::errorUrl('invalid-topic-pattern'),
 * ));
 * ```
 */
final class Documentation
{
    /**
     * Адрес опубликованного сайта документации.
     */
    public const BASE_URL = 'https://selyusize.github.io/events-router/';

    /**
     * Ссылка на страницу с описанием ошибки.
     *
     * @param non-empty-string $slug имя страницы в `docs/errors/` без расширения, в kebab-case
     *
     * @return non-empty-string
     */
    public static function errorUrl(string $slug): string
    {
        return self::BASE_URL . 'errors/' . $slug . '/';
    }
}
