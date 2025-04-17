#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Client\GeoLocationConfig;
use App\Infrastructure\Console\UpdateGeoIPCommand;
use App\Infrastructure\DI\Container;
use Psr\Log\LoggerInterface;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new Container();
$containerConfig($container);

$config = $container->get(GeoLocationConfig::class);
$logger = $container->get(LoggerInterface::class);
$command = new UpdateGeoIPCommand($config, $logger);

// Выполняем команду
$command->execute();

echo "Обновление базы данных геолокации завершено.\n";
