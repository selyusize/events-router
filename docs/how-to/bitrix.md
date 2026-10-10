# Подключить события Bitrix

В проекте на Bitrix обработчики событий обычно живут в `init.php` или `events.php`: строка `AddEventHandler(...)` за строкой, классы и методы — строками, `&$arFields` — по ссылке, отмена — через `return false` и `$APPLICATION->ThrowException()`. Пока обработчиков десять, это терпимо. Когда их сотня, уже никто не скажет, что именно произойдёт при сохранении товара.

Мост `BitrixEventBridge` переносит события Bitrix в тот же файл маршрутов, где живут ваши собственные события. Слушатели, middleware, порядок вызова, лог и обработка ошибок — те же самые.

## 1. Опишите маршруты

События Bitrix — в группе `bitrix`, топик — `<модуль>.<Событие>`, как в Bitrix:

```php
--8<-- "bitrix/bridge.php:routes"
```

Каждая строка — одно конкретное событие. Шаблоны вроде `sale.*` здесь не работают: `EventManager` Bitrix подписывается только на точные пары «модуль + событие». Такой маршрут даст [`InvalidRoute`](../errors/invalid-route.md) при подключении моста.

## 2. Напишите слушателей

Слушатель — обычный `ListenerInterface`. Данные события Bitrix лежат в payload — объекте `BitrixEvent`:

```php
--8<-- "bitrix/bridge.php:listeners"
```

Bitrix вызывает обработчики двумя способами. `BitrixEvent` понимает оба:

| Как Bitrix вызвал событие | Чем читать | Как отменить |
| --- | --- | --- |
| старый API: `GetModuleEvents()`, `&$arFields` | `getFields()`, `setField()`, `getArguments()` | `cancel($reason)` → Bitrix получит `false`, причина — в `$APPLICATION->GetException()` |
| D7: `Bitrix\Main\Event::send()` | `getD7Event()` | `cancel($reason)` → Bitrix получит `EventResult::ERROR` с причиной в параметрах |

Какой стиль у конкретного события, смотрите в документации Bitrix к нему. Если перепутать, `getFields()` у события D7 честно скажет об этом исключением, а не вернёт пустой массив.

## 3. Подключите мост

В проекте — одна строка в `local/php_interface/include/events.php`:

```php
use Bitrix\Main\EventManager;
use Rasa\Container\Container;
use Selyusize\EventsRouter\Bitrix\BitrixEventBridge;
use Selyusize\EventsRouter\EventRouter;

BitrixEventBridge::attachLazy(
    EventManager::getInstance(),
    static fn (): EventRouter => Container::get(EventRouter::class),
    $_SERVER['DOCUMENT_ROOT'] . '/local/var/cache/bitrix-events.php',
);
```

`init.php` выполняется на каждом хите сайта. Собирать на каждом хите роутер, контейнер и все маршруты ради того, чтобы подписаться на пару событий, — дорого. Поэтому `attachLazy()`:

- берёт список событий из файла и подписывается только на них;
- собирает роутер, только когда событие Bitrix действительно произошло;
- если файла нет, собирает роутер сразу и записывает список. Папка создаётся сама.

**После изменения маршрутов файл нужно удалить**, например на деплое. Иначе мост не узнает о новых событиях.

Для консольных скриптов и разработки есть `attach()` — подписка сразу по маршрутам роутера, без файла:

```php
--8<-- "bitrix/bridge.php:attach"
```

## Что получится

Bitrix вызывает события так же, как всегда, — мост ничего в нём не меняет:

```php
--8<-- "bitrix/bridge.php:fire"
```

```text
--8<-- "bitrix/bridge.out"
```

## Если id модуля с точкой

У партнёрских модулей id содержит точку: `rasa.shop`. А точка — разделитель сегментов топика. Для таких модулей соберите топик через `topic()`, она закодирует точку как `~`:

```php
$bitrix->listen(BitrixEventBridge::topic('rasa.shop', 'OnOrderExport'), ExportOrder::class);
// топик: bitrix.rasa~shop.OnOrderExport
```

## Если у роутера есть префикс

Мост ищет маршруты, топик которых начинается с `bitrix.`. Если роутеру задан `setPrefix('shop')`, полный топик станет `shop.bitrix.main.OnAfterUserAdd` — передайте префикс мосту последним аргументом: `attach($manager, $events, 'shop.bitrix')`.

## Что важно помнить

- **Ошибка слушателя не роняет страницу.** По умолчанию исключение попадает в отчёт и в [лог роутера](logging.md), остальные слушатели работают. Отменить действие можно только явно — через `cancel()`.
- **После `cancel()` остальные слушатели не вызываются.** `BitrixEvent` реализует PSR-14 `StoppableEventInterface`, в отчёте у них статус `Skipped`.
- **`setField()` меняет данные Bitrix.** Это осознанное исключение из неизменяемости событий: в `OnBefore*` весь смысл в том, чтобы поправить поля. Изменения одного слушателя видят следующие.
- **Свои события из событий Bitrix.** Хороший приём — слушатель, который переводит событие Bitrix на язык домена: получил `sale.OnSaleOrderPaid` и разослал свой `shop.order.42.paid`. Тогда бизнес-логика вообще не знает о Bitrix.
