<?php

declare(strict_types=1);

// Autoloading and English library messages (exceptions, log) for all English examples

require_once __DIR__ . '/../../vendor/autoload.php';

use Selyusize\EventsRouter\EventRouterFactory;

EventRouterFactory::create(config: ['locale' => 'en']);
