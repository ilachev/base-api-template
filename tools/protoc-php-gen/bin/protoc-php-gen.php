#!/usr/bin/env php
<?php

declare(strict_types=1);

// Автозагрузка классов из vendor
$autoloadFile = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    fwrite(STDERR, "Автозагрузчик не найден: {$autoloadFile}\n");
    fwrite(STDERR, "Пожалуйста, выполните 'composer install' в директории tools/protoc-php-gen\n");
    exit(1);
}

require_once $autoloadFile;

use ProtoPhpGen\PhpGeneratorPlugin;

// Запускаем плагин
$plugin = new PhpGeneratorPlugin();
exit($plugin->run());
