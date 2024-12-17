<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Infrastructure\DI\Container;
use App\Application\Handlers\HandlerInterface;
use App\Application\Http\RequestHandler;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Routing\Router;
use Nyholm\Psr7\Response;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

/** @var callable(Container<object>): void $containerConfig */
$containerConfig = require __DIR__ . '/config/container.php';

$container = new Container();
$containerConfig($container);

$worker = Worker::create();
$psr7 = $container->get(PSR7Worker::class);
$router = $container->get(Router::class);
$authMiddleware = $container->get(AuthMiddleware::class);

while (true) {
    try {
        $request = $psr7->waitRequest();
        if ($request === null) {
            break;
        }

        $routeResult = $router->dispatch($request);
        if (!$routeResult->isFound()) {
            $errorJson = json_encode(['error' => 'Route not found']);
            if ($errorJson === false) {
                $errorJson = '{"error":"Route not found"}';
            }

            $psr7->respond(new Response(
                $routeResult->getStatusCode(),
                ['Content-Type' => 'application/json'],
                $errorJson
            ));
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
    } catch (\Throwable $e) {
        $psr7->respond(new Response(500, [], 'Internal Server Error'));
        $worker->error((string)$e);
    }
}
