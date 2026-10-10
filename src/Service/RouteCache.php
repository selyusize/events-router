<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Service;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Exception\InvalidRoute;
use Selyusize\EventsRouter\Locale\Messages;
use Selyusize\EventsRouter\Routing\RouteTable;
use Throwable;

/**
 * Кэш маршрутов в PHP-файле: собранная таблица и middleware роутера из файла маршрутов.
 *
 * Файл — обычный `return [...]`, поэтому его кэширует OPcache и чтение почти бесплатно.
 * Шаблоны хранятся уже скомпилированными в регулярные выражения: из кэша они не разбираются заново.
 *
 * @internal кэш подключается ключом `route_cache_file` в EventRouterFactory::create()
 */
final class RouteCache
{
    /**
     * Версия формата. Файл другой версии считается устаревшим: роутер соберёт маршруты заново.
     */
    private const FORMAT = 2;

    /**
     * @param non-empty-string $file
     */
    public function __construct(
        private readonly string $file,
    ) {}

    /**
     * Таблица и middleware роутера из файла или null, если файла нет, он повреждён или старого формата.
     *
     * Таблица берётся как есть, без разбора шаблонов и без создания объектов: с OPcache
     * массив из файла даже не копируется в память запроса.
     *
     * @return array{RouteTable, list<class-string<MiddlewareInterface>>}|null
     */
    public function load(): ?array
    {
        try {
            // Без is_file(): лишнее обращение к диску на каждом запросе. Нет файла — include вернёт false
            /** @psalm-suppress UnresolvableInclude путь задаёт пользователь */
            $data = @include $this->file;
        } catch (Throwable) {
            return null;
        }

        if (!\is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            return null;
        }

        /** @var array{format: int, table: array{routes: list<array{0: non-empty-string, 1: non-empty-string, 2: class-string<ListenerInterface>, 3: list<class-string<MiddlewareInterface>>}>, exact: array<string, list<int>>, prefixes: array<string, list<int>>}, middleware: list<class-string<MiddlewareInterface>>} $data файл пишет save() */
        return [RouteTable::fromExport($data['table']), $data['middleware']];
    }

    /**
     * Записать таблицу и middleware роутера в файл.
     *
     * Пишется через временный файл: параллельный запрос не прочитает недописанный кэш.
     * Если записать не удалось, маршруты всё равно работают, а PHP получает предупреждение.
     *
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middleware middleware роутера из файла маршрутов
     *
     * @throws InvalidRoute если middleware добавлен объектом: объект в файл не записать
     */
    public function save(RouteTable $table, array $middleware): void
    {
        $data = $table->export();
        $all = $middleware;

        foreach ($data['routes'] as $route) {
            $all = [...$all, ...$route[3]];
        }

        foreach ($all as $item) {
            if (!\is_string($item)) {
                throw InvalidRoute::because(\sprintf(
                    Messages::translate('кэш маршрутов хранит middleware только именем класса, а %s добавлен объектом: передайте в add() имя класса'),
                    $item::class,
                ));
            }
        }

        $code = "<?php\n\n// " . Messages::translate('Кэш маршрутов events-router. Удалите файл после изменения маршрутов.') . "\n\nreturn "
            . var_export(['format' => self::FORMAT, 'table' => $data, 'middleware' => $middleware], true)
            . ";\n";

        $directory = \dirname($this->file);
        $temporary = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if ((!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory))
            || @file_put_contents($temporary, $code) === false
            || !@rename($temporary, $this->file)) {
            trigger_error(\sprintf(Messages::translate('events-router: не удалось записать кэш маршрутов в %s'), $this->file), E_USER_WARNING);

            return;
        }

        // Если OPcache не проверяет время изменения файлов, без этого он отдаст старую версию
        if (\function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->file, true);
        }
    }
}
