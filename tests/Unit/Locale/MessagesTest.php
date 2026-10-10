<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Tests\Unit\Locale;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClassConstant;
use Selyusize\EventsRouter\Event;
use Selyusize\EventsRouter\EventRouterFactory;
use Selyusize\EventsRouter\Exception\InvalidConfig;
use Selyusize\EventsRouter\Exception\InvalidEventName;
use Selyusize\EventsRouter\Exception\InvalidRoute;
use Selyusize\EventsRouter\Locale\LocaleEnum;
use Selyusize\EventsRouter\Locale\Messages;
use SplFileInfo;

/**
 * @internal
 */
final class MessagesTest extends TestCase
{
    private const SOURCE_DIR = __DIR__ . '/../../../src';

    protected function tearDown(): void
    {
        Messages::setLocale(LocaleEnum::Ru);
    }

    public function testEveryTranslatedTextHasEnglishVersion(): void
    {
        $missing = array_diff(self::translatedInSource(), array_keys(self::english()));

        self::assertSame([], array_values($missing), 'Нет перевода в Messages::ENGLISH');
    }

    public function testEveryEnglishVersionIsUsed(): void
    {
        $unused = array_diff(array_keys(self::english()), self::translatedInSource());

        self::assertSame([], array_values($unused), 'Перевод в Messages::ENGLISH никто не использует');
    }

    public function testEnglishKeepsPlaceholders(): void
    {
        foreach (self::english() as $russian => $english) {
            preg_match_all('/%(?:\d+\$)?[.\d]*[sdf]/', $russian, $expected);
            preg_match_all('/%(?:\d+\$)?[.\d]*[sdf]/', $english, $actual);

            self::assertSame($expected[0], $actual[0], $russian);
        }
    }

    public function testRussianByDefault(): void
    {
        EventRouterFactory::create();

        $this->expectException(InvalidEventName::class);
        $this->expectExceptionMessage('Некорректное имя события "order paid": имя содержит пробельные символы. См. https://selyusize.github.io/events-router/errors/invalid-event-name/');

        new Event('order paid');
    }

    public function testEnglishFromConfig(): void
    {
        EventRouterFactory::create(null, ['locale' => 'en']);

        $this->expectException(InvalidEventName::class);
        $this->expectExceptionMessage('Invalid event name "order paid": name contains whitespace. See https://selyusize.github.io/events-router/en/errors/invalid-event-name/');

        new Event('order paid');
    }

    public function testEnglishRouteError(): void
    {
        $events = EventRouterFactory::create(null, ['locale' => 'en']);

        $this->expectException(InvalidRoute::class);
        $this->expectExceptionMessage('Route error: listener class "App\Missing" not found. See https://selyusize.github.io/events-router/en/errors/invalid-route/');

        $events->listen('order.paid', 'App\Missing');
    }

    public function testCreateWithoutLocaleKeepsLanguage(): void
    {
        EventRouterFactory::create(null, ['locale' => 'en']);
        EventRouterFactory::create();

        self::assertSame(LocaleEnum::En, Messages::getLocale());
    }

    public function testConfigErrorsAfterLocaleAreInThatLocale(): void
    {
        $this->expectException(InvalidConfig::class);
        $this->expectExceptionMessage('Invalid router config: unknown key "log", allowed:');

        EventRouterFactory::create(null, ['locale' => 'en', 'log' => true]);
    }

    public function testUnknownLocale(): void
    {
        $this->expectException(InvalidConfig::class);
        $this->expectExceptionMessage('locale должен быть одним из "ru", "en", передано "de"');

        EventRouterFactory::create(null, ['locale' => 'de']);
    }

    /**
     * @return array<string, string>
     */
    private static function english(): array
    {
        /** @var array<string, string> */
        return (new ReflectionClassConstant(Messages::class, 'ENGLISH'))->getValue();
    }

    /**
     * Тексты из вызовов Messages::translate('...') во всём src.
     *
     * @return list<string>
     */
    private static function translatedInSource(): array
    {
        $texts = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SOURCE_DIR, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            preg_match_all("/Messages::translate\\('([^']+)'\\)/", (string)file_get_contents($file->getPathname()), $matches);
            $texts = [...$texts, ...$matches[1]];
        }

        return array_values(array_unique($texts));
    }
}
