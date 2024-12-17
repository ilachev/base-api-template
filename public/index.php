<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Error\ApiError;
use App\Infrastructure\DI\Container;
use App\Application\Handlers\HandlerInterface;
use App\Application\Http\RequestHandler;
use App\Application\Http\JsonResponse;
use App\Application\Middleware\AuthMiddleware;
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
        $requestHandler = new RequestHandler($handler);
        $request = $request->withAttribute('routeParams', $routeResult->getParams());

        $response = $authMiddleware->process($request, $requestHandler);
        $psr7->respond($response);
    } catch (Throwable $e) {
        $response = $jsonResponse->error(ApiError::INTERNAL_ERROR, 500);
        $psr7->respond($response);
        $worker->error((string)$e);
    }
}
