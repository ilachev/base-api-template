#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Console\CacheClearCommand;
use App\Infrastructure\DI\Container;
use Psr\Log\LoggerInterface;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new Container();
$containerConfig($container);

$cacheService = $container->get(CacheService::class);
$logger = $container->get(LoggerInterface::class);
$command = new CacheClearCommand($cacheService, $logger);

$command->clear();
