<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Error\ApiError;
use App\Infrastructure\DI\Container;
use App\Application\Handlers\HandlerInterface;
use App\Application\Http\RequestHandler;
use App\Application\Http\JsonResponse;
use App\Application\Middleware\Pipeline;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Middleware\HttpLoggingMiddleware;
use App\Application\Routing\Router;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/../config/container.php';

$container = new Container();
$containerConfig($container);

$worker = $container->get(Worker::class);
$psr7 = $container->get(PSR7Worker::class);
$router = $container->get(Router::class);
$authMiddleware = $container->get(AuthMiddleware::class);
$httpLoggingMiddleware = $container->get(HttpLoggingMiddleware::class);
$jsonResponse = $container->get(JsonResponse::class);

while (true) {
    try {
        $request = $psr7->waitRequest();
        if ($request === null) {
            break;
        }

        $routeResult = $router->dispatch($request);
        if (!$routeResult->isFound()) {
            $response = $jsonResponse->error(
                ApiError::NOT_FOUND,
                $routeResult->getStatusCode()
            );
            $psr7->respond($response);
            continue;
        }

        /** @var class-string<HandlerInterface> $handlerClass */
        $handlerClass = $routeResult->getHandler();
        /** @var HandlerInterface $handler */
        $handler = $container->get($handlerClass);
        $request = $request
            ->withAttribute('requestId', uniqid())
            ->withAttribute('routeParams', $routeResult->getParams())
        ;

        $response = new Pipeline(
            new RequestHandler($handler),
            [$authMiddleware, $httpLoggingMiddleware]
        )->handle($request);

        $psr7->respond($response);
    } catch (Throwable $e) {
        $response = $jsonResponse->error(ApiError::INTERNAL_ERROR, 500);
        $psr7->respond($response);
        $worker->error((string)$e);
    }
}
