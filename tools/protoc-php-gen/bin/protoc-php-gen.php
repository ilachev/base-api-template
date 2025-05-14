#!/usr/bin/env php
<?php

declare(strict_types=1);

// Автозагрузка классов из vendor
// Пытаемся найти файл автозагрузки
$scriptDir = __DIR__;

// Сначала проверяем наличие в стандартных местах
$possiblePaths = [
    $scriptDir . '/../vendor/autoload.php',
    $scriptDir . '/tools/protoc-php-gen/vendor/autoload.php',
    dirname($scriptDir) . '/vendor/autoload.php',
    dirname(dirname($scriptDir)) . '/tools/protoc-php-gen/vendor/autoload.php',
];

$autoloadFile = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $autoloadFile = $path;
        break;
    }
}

if ($autoloadFile === null) {
    fwrite(STDERR, "Автозагрузчик не найден.\n");
    fwrite(STDERR, "Пожалуйста, выполните 'composer install' в директории tools/protoc-php-gen\n");
    exit(1);
}

require_once $autoloadFile;

use ProtoPhpGen\PhpGeneratorPlugin;

// Запускаем плагин
$plugin = new PhpGeneratorPlugin();
exit($plugin->run());
