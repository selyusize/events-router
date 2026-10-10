<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Locale;

/**
 * Язык сообщений библиотеки: исключений, лога и предупреждений.
 * Значение — то, что пишется в ключ конфига `locale`.
 */
enum LocaleEnum: string
{
    /**
     * Русский, по умолчанию.
     */
    case Ru = 'ru';

    case En = 'en';
}
