<?php

require __DIR__ . '/vendor/autoload.php';

use App\Application\Routing\Router;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use App\Application\Http\RequestHandler;
use App\Application\Middleware\AuthMiddleware;

$worker = Worker::create();
$factory = new Psr17Factory();
$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

$router = new Router();
$authMiddleware = new AuthMiddleware();

while (true) {
    try {
        $request = $psr7->waitRequest();
        if ($request === null) {
            break;
        }

        $routeResult = $router->dispatch($request);

        if (!$routeResult->isFound()) {
            $psr7->respond(new Response(
                $routeResult->getStatusCode(),
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Route not found'])
            ));
            continue;
        }

        $handlerClass = $routeResult->getHandler();
        $handler = new $handlerClass();
        $requestHandler = new RequestHandler($handler);

        // Добавляем параметры маршрута к атрибутам запроса
        $request = $request->withAttribute('routeParams', $routeResult->getParams());

        $response = $authMiddleware->process($request, $requestHandler);
        $psr7->respond($response);

    } catch (\Throwable $e) {
        $psr7->respond(new Response(500, [], 'Internal Server Error'));
        $worker->error((string)$e);
    }
}
