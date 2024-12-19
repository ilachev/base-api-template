<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Http\RouteHandlerResolver;
use App\Infrastructure\DI\Container;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use App\Application\Middleware\Pipeline;
use App\Application\Middleware\ErrorHandlerMiddleware;
use App\Application\Middleware\RoutingMiddleware;
use App\Application\Middleware\RequestIdMiddleware;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Middleware\HttpLoggingMiddleware;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new Container();
$containerConfig($container);

$worker = $container->get(Worker::class);
$psr7 = $container->get(PSR7Worker::class);

$middlewares = [
    $container->get(ErrorHandlerMiddleware::class),
    $container->get(RoutingMiddleware::class),
    $container->get(RequestIdMiddleware::class),
    $container->get(AuthMiddleware::class),
    $container->get(HttpLoggingMiddleware::class),
];

while (true) {
    $request = $psr7->waitRequest();
    if ($request === null) {
        break;
    }

    $pipeline = new Pipeline(
        $container->get(RouteHandlerResolver::class),
        $middlewares
    );

    $response = $pipeline->handle($request);
    $psr7->respond($response);
}
