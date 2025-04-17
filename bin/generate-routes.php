#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Routing\Generator\ProtoRouteProvider;
use App\Infrastructure\Routing\Generator\RoutesWriter;

// Configuration
$protoDir = __DIR__ . '/../protos/proto';
$outputFile = __DIR__ . '/../config/routes.php';

// Configure service to handler mapping (optional)
$handlerMapping = [
    // 'CustomService.Method' => 'App\Application\Handlers\CustomHandler',
];

// Generate routes
$provider = new ProtoRouteProvider($protoDir, $handlerMapping);
$writer = new RoutesWriter($provider, $outputFile);

try {
    $writer->generateRoutesFile();
    echo "Routes configuration has been successfully generated to {$outputFile}\n";
} catch (Throwable $e) {
    echo "Error generating routes: {$e->getMessage()}\n";
    exit(1);
}
