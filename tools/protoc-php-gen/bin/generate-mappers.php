#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use ProtoPhpGen\Generator\ProtoDomainMapperGenerator;
use ProtoPhpGen\Parser\AttributeParser;

// Конфигурация генератора
$domainDir = __DIR__ . '/../../../src/Domain';
$protoDir = __DIR__ . '/../../../protos/proto';
$outputDir = __DIR__ . '/../../../gen/Infrastructure/Hydrator';
$domainNamespace = 'App\Domain';
$protoNamespace = 'App\Api';

// Создаем директорию для гидраторов, если она не существует
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0o755, true);
}

$attributeParser = new AttributeParser();
$generator = new ProtoDomainMapperGenerator();

echo "Scanning domain classes in {$domainDir}\n";

// Поиск PHP-файлов в доменной директории
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($domainDir),
);

$classNames = [];
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            continue;
        }

        // Получаем пространство имен
        $namespaceMatches = [];
        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
        if (empty($namespaceMatches)) {
            continue;
        }
        $namespace = $namespaceMatches[1];

        // Получаем имя класса
        $classMatches = [];
        preg_match('/class\s+([^\s{]+)/', $content, $classMatches);
        if (empty($classMatches)) {
            continue;
        }
        $className = $classMatches[1];

        // Полное имя класса
        $classNames[] = $namespace . '\\' . $className;
    }
}

// Загружаем и обрабатываем классы
$generatedFiles = 0;
foreach ($classNames as $className) {
    try {
        if (!class_exists($className)) {
            // Пытаемся загрузить класс
            @include_once str_replace('\\', '/', $className) . '.php';
        }

        if (!class_exists($className, false)) {
            continue;
        }

        // Анализируем атрибуты и создаем маппинг
        $mapping = $attributeParser->parse($className);
        if ($mapping !== null) {
            echo "Found mapping for class: {$className} -> {$mapping->getProtoClass()}\n";

            // Генерируем гидратор
            $outputPath = $generator->generateFromMapping($mapping, $outputDir);
            echo "Generated: {$outputPath}\n";
            ++$generatedFiles;
        }
    } catch (Throwable $e) {
        echo "Error processing {$className}: {$e->getMessage()}\n";
    }
}

echo "Generation completed. Generated {$generatedFiles} files.\n";
