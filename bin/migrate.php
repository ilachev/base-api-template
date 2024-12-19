#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Console\MigrateCommand;
use App\Infrastructure\DI\Container;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new Container();
$containerConfig($container);

$service = $container->get(\App\Infrastructure\Storage\Migration\MigrationService::class);
$command = new MigrateCommand($service);

$action = $argv[1] ?? 'migrate';

match ($action) {
    'migrate' => $command->migrate(),
    'rollback' => $command->rollback(),
    default => throw new InvalidArgumentException('Invalid action. Use migrate or rollback'),
};
