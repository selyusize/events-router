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

## Свои события рядом с событиями Bitrix {#own-events}

Событий Bitrix проекту обычно мало: «заказ оплачен» в бизнес-смысле, «бонусы начислены», «выгрузка завершена» Bitrix не вызывает. Такие события объявляются в том же файле маршрутов, рядом с группой `bitrix`, а рассылает их `BitrixEventBridge::dispatch()`:

```php
--8<-- "bitrix/own-events.php:routes"
```

`dispatch()` отправляет событие в тот же роутер, к которому подключён мост: маршруты, middleware, лог и обработка ошибок общие. Вызывать его можно отовсюду — из компонента, контроллера, агента и из слушателя события Bitrix. Последнее — хороший приём: слушатель переводит событие Bitrix на язык домена, и бизнес-логика вообще не знает о Bitrix:

```php
--8<-- "bitrix/own-events.php:listeners"
```

```php
--8<-- "bitrix/own-events.php:fire"
```

```text
--8<-- "bitrix/own-events.out"
```

Что важно знать о `dispatch()`:

- **Роутер собирается один раз на процесс.** С `attachLazy()` — при первом событии, своём или Bitrix. Если за хит не произошло ни одного события, роутер не собирается вовсе.
- **Сначала подключите мост.** `dispatch()` до `attach()` или `attachLazy()` бросает `LogicException`: роутера, в который отправить событие, ещё нет. В проекте мост подключается в `init.php`, так что к компонентам и контроллерам он уже готов.
- **Свои события — не под префиксом `bitrix`.** События `bitrix.*` вызывает сам Bitrix, и слушатели этой группы ждут `BitrixEvent` в payload. `dispatch(new Event('bitrix.main.OnAfterUserAdd'))` бросит `LogicException`. Если нужно вызвать событие Bitrix, вызовите его через Bitrix: `(new Bitrix\Main\Event('sale', 'OnSaleOrderPaid', [...]))->send()`.
- **Не берите роутер из контейнера без определения.** `Container::get(EventRouter::class)` в контейнере с автосвязыванием, где роутер не зарегистрирован, создаст новый пустой роутер без маршрутов: событие молча никуда не уйдёт. `dispatch()` такой ошибки не допускает.

## Посмотреть все обработчики события

Слушатели роутера — не единственные, кто подписан на событие Bitrix. Рядом работают обработчики из `init.php`, из модулей ядра и партнёрских модулей. `getHandlers()` показывает их все в том порядке, в каком их вызовет Bitrix — список берётся из `EventManager`, а не из маршрутов:

```php
$bitrix->getHandlers();
// [
//     'CLists::OnBeforeIBlockElementUpdate (lists)',      // модуль из b_module_to_module
//     'App\\Catalog\\Listener\\NormalizeElement\\Handler::handle',
//     'Selyusize\\EventsRouter\\Bitrix\\BitrixEventBridge::bitrix.iblock.OnBeforeIBlockElementUpdate',
//     'updatePreviewText',                                // функция
//     'Closure /local/php_interface/init.php:42',
// ]
```

Мост роутера тоже в списке: по его месту видно, какие обработчики сработают до слушателей роутера, а какие — после. Удобно писать список в лог из middleware группы `bitrix`, когда нужно понять, кто ещё трогает поля при сохранении товара.

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
- **Свои события — через `BitrixEventBridge::dispatch()`.** Тот же роутер, те же маршруты, см. [Свои события рядом с событиями Bitrix](#own-events).
