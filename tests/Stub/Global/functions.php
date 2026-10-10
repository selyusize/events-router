<?php

declare(strict_types=1);

use Bitrix\Main\EventManager;

/*
 * Заглушки старого API событий Bitrix. Так Bitrix вызывает, например, OnBeforeIBlockElementUpdate:
 *
 *     foreach (GetModuleEvents('iblock', 'OnBeforeIBlockElementUpdate', true) as $arEvent) {
 *         if (ExecuteModuleEventEx($arEvent, [&$arFields]) === false) { ... }
 *     }
 */

if (!function_exists('GetModuleEvents')) {
    /**
     * @return list<array<string, mixed>>
     */
    function GetModuleEvents(string $moduleId, string $messageId, bool $returnArray = false): array
    {
        return EventManager::getInstance()->findEventHandlers($moduleId, $messageId);
    }

    /**
     * Как в Bitrix: версия обработчика не учитывается, аргументы передаются как есть — со ссылками.
     *
     * @param array<string, mixed> $arEvent
     * @param array<array-key, mixed> $arParams
     */
    function ExecuteModuleEventEx(array $arEvent, array $arParams = []): mixed
    {
        /** @var callable $callback */
        $callback = $arEvent['CALLBACK'];

        return call_user_func_array($callback, $arParams);
    }
}
