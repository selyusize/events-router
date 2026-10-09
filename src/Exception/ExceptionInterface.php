<?php

declare(strict_types=1);

namespace Selyusize\EventsRouter\Exception;

use Throwable;

/**
 * Общий интерфейс всех исключений библиотеки.
 *
 * Позволяет поймать любую ошибку events-router одним catch,
 * не перехватывая исключения слушателей и самого приложения.
 *
 * @example
 * try {
 *     $events->dispatch($event);
 * } catch (ExceptionInterface $error) {
 *     // ошибка конфигурации или работы роутера
 * }
 */
interface ExceptionInterface extends Throwable {}
