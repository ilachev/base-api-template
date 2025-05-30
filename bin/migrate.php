#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Console\MigrateCommand;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\DIContainer;
use App\Infrastructure\Storage\Migration\MigrationService;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new DIContainer();
$containerConfig($container);

$service = $container->get(MigrationService::class);
$command = new MigrateCommand($service);

$action = $argv[1] ?? 'migrate';

match ($action) {
    'migrate' => $command->migrate(),
    'rollback' => $command->rollback(),
    default => throw new InvalidArgumentException('Invalid action. Use migrate or rollback'),
};
