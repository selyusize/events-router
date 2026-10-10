<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Routing;

use Selyusize\EventsRouter\Contract\Core\ListenerInterface;
use Selyusize\EventsRouter\Contract\Core\MiddlewareInterface;
use Selyusize\EventsRouter\Topic\TopicPattern;

/**
 * Таблица собранных маршрутов в порядке объявления. Неизменяемая.
 *
 * Поиск не перебирает все маршруты. Маршруты без `{…}`, `*` и `#` лежат в хэш-таблице по точному
 * топику — как слушатели в symfony/event-dispatcher. Остальные разложены по литеральному началу
 * шаблона (`shop.order` у `shop.order.{order_id}.paid`): регулярное выражение проверяется только у тех,
 * чьё начало совпало с началом топика. Результат для топика запоминается.
 *
 * Внутри таблица — массивы, а не объекты: так её целиком можно сохранить в кэш и прочитать
 * без разбора. Объекты маршрутов создаются, только когда маршрут совпал с событием или их запросили.
 *
 * @psalm-type RouteData = array{0: non-empty-string, 1: non-empty-string, 2: class-string<ListenerInterface>, 3: list<class-string<MiddlewareInterface>|MiddlewareInterface>}
 * @psalm-type TableData = array{routes: list<RouteData>, exact: array<string, list<int>>, prefixes: array<string, list<int>>}
 */
final class RouteTable
{
    /**
     * Сколько топиков помнить. Топиков с параметрами (`order.42.paid`) бесконечно много,
     * поэтому при переполнении память очищается целиком.
     */
    private const REMEMBERED_TOPICS = 1024;

    /**
     * @var array<int, CompiledRoute> объекты маршрутов, которые уже понадобились
     */
    private array $compiled = [];

    /**
     * @var array<string, array{matches: list<RouteMatch>, listeners: list<class-string<ListenerInterface>>, middleware: list<list<class-string<MiddlewareInterface>|MiddlewareInterface>>, parameters: list<array<non-empty-string, non-empty-string>>, direct: bool}>
     */
    private array $remembered = [];

    /**
     * @param TableData $data маршруты (шаблон, regex, слушатель, middleware) и индекс для поиска:
     *                        `exact` — точный топик → номера маршрутов без шаблонных сегментов,
     *                        `prefixes` — литеральное начало шаблона → номера остальных маршрутов
     */
    private function __construct(
        private readonly array $data,
    ) {}

    /**
     * Таблица из собранных маршрутов: строит индекс для поиска.
     *
     * @internal таблицу строит RouteTableBuilder
     *
     * @param list<CompiledRoute> $routes
     */
    public static function fromRoutes(array $routes): self
    {
        $data = ['routes' => [], 'exact' => [], 'prefixes' => []];

        foreach ($routes as $index => $route) {
            $pattern = $route->getPattern()->getPattern();
            $data['routes'][] = [$pattern, $route->getPattern()->getRegex(), $route->getListener(), $route->getMiddleware()];

            if (strpbrk($pattern, '{*#') === false) {
                $data['exact'][$pattern][] = $index;

                continue;
            }

            // Литеральные сегменты до первого шаблонного. Точка внутри {…} встретится только после него
            $literal = [];

            foreach (explode('.', $pattern) as $segment) {
                if (strpbrk($segment, '{*#') !== false) {
                    break;
                }

                $literal[] = $segment;
            }

            $data['prefixes'][implode('.', $literal)][] = $index;
        }

        $table = new self($data);
        $table->compiled = $routes;

        return $table;
    }

    /**
     * Таблица из данных, которые вернул export(): без разбора шаблонов и без объектов.
     *
     * @internal используется кэшем маршрутов
     *
     * @param TableData $data
     */
    public static function fromExport(array $data): self
    {
        return new self($data);
    }

    /**
     * Данные таблицы для кэша.
     *
     * @internal используется кэшем маршрутов
     *
     * @return TableData
     */
    public function export(): array
    {
        return $this->data;
    }

    /**
     * @return list<CompiledRoute>
     */
    public function getRoutes(): array
    {
        return array_map($this->route(...), array_keys($this->data['routes']));
    }

    /**
     * Все маршруты, чей шаблон подходит под топик, строго в порядке объявления.
     *
     * В отличие от HTTP-роутера, возвращает **все** совпадения: у события может быть
     * сколько угодно слушателей.
     *
     * @return list<RouteMatch> пустой список, если слушателей нет
     */
    public function match(string $topic): array
    {
        return $this->plan($topic)['matches'];
    }

    /**
     * Совпадения и всё, что нужно для рассылки, разложенное по массивам: слушатель, его middleware
     * и параметры маршрута по номеру совпадения. Рассылка берёт их по индексу, без вызовов методов.
     * `direct` — ни у одного слушателя нет ни middleware, ни параметров: их можно вызывать подряд.
     *
     * @internal используется Dispatcher
     *
     * @return array{
     *     matches: list<RouteMatch>,
     *     listeners: list<class-string<ListenerInterface>>,
     *     middleware: list<list<class-string<MiddlewareInterface>|MiddlewareInterface>>,
     *     parameters: list<array<non-empty-string, non-empty-string>>,
     *     direct: bool
     * }
     */
    public function plan(string $topic): array
    {
        if (isset($this->remembered[$topic])) {
            return $this->remembered[$topic];
        }

        // Кандидаты: точные маршруты и шаблоны, чьё литеральное начало — начало топика
        $exact = $this->data['exact'][$topic] ?? [];
        $candidates = $exact;
        $sources = $exact === [] ? 0 : 1;
        $prefix = '';
        $segments = explode('.', $topic);

        for ($i = 0, $count = \count($segments); $i <= $count; ++$i) {
            if (isset($this->data['prefixes'][$prefix])) {
                $candidates = [...$candidates, ...$this->data['prefixes'][$prefix]];
                ++$sources;
            }

            if ($i < $count) {
                $prefix = $i === 0 ? $segments[0] : $prefix . '.' . $segments[$i];
            }
        }

        // Из разных групп номера идут не по порядку, а слушатели вызываются в порядке объявления
        if ($sources > 1) {
            sort($candidates);
        }

        $exact = array_flip($exact);
        $plan = ['matches' => [], 'listeners' => [], 'middleware' => [], 'parameters' => [], 'direct' => true];

        foreach ($candidates as $index) {
            $route = $this->route($index);
            $parameters = isset($exact[$index]) ? [] : $route->getPattern()->match($topic);

            if ($parameters !== null) {
                $plan['matches'][] = new RouteMatch($route, $parameters);
                $plan['listeners'][] = $this->data['routes'][$index][2];
                $plan['middleware'][] = $this->data['routes'][$index][3];
                $plan['parameters'][] = $parameters;
                // Хоть у одного слушателя есть параметры или middleware — нужен полный цикл рассылки
                $plan['direct'] = $plan['direct'] && $parameters === [] && $this->data['routes'][$index][3] === [];
            }
        }

        if (\count($this->remembered) >= self::REMEMBERED_TOPICS) {
            $this->remembered = [];
        }

        return $this->remembered[$topic] = $plan;
    }

    private function route(int $index): CompiledRoute
    {
        if (!isset($this->compiled[$index])) {
            [$pattern, $regex, $listener, $middleware] = $this->data['routes'][$index];
            $this->compiled[$index] = new CompiledRoute(TopicPattern::fromCache($pattern, $regex), $listener, $middleware);
        }

        return $this->compiled[$index];
    }
}
