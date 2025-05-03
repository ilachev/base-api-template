#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Console\CacheClearCommand;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\DIContainer;
use App\Infrastructure\Logger\Logger;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new DIContainer();
$containerConfig($container);

$cacheService = $container->get(CacheService::class);
$logger = $container->get(Logger::class);
$command = new CacheClearCommand($cacheService, $logger);

// Запускаем команду и используем результат для выставления статуса завершения скрипта
$success = $command->clear();

// Возвращаем ненулевой статус, если очистка не удалась
exit($success ? 0 : 1);
