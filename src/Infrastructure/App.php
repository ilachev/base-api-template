<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Http\RouteHandlerResolver;
use App\Application\Middleware\{
    ErrorHandlerMiddleware,
    HttpLoggingMiddleware,
    Pipeline,
    RequestMetricsMiddleware,
    RoutingMiddleware,
    SessionMiddleware
};
use App\Infrastructure\DI\Container;
use Spiral\RoadRunner\Http\PSR7Worker;

/** @template T of object */
final readonly class App
{
    /** @var Container<T> */
    private Container $container;

    private PSR7Worker $worker;

    private Pipeline $pipeline;

    public function __construct(string $configPath)
    {
        /** @var callable(Container<T>): void $containerConfig */
        $containerConfig = require $configPath;

        $this->container = new Container();
        $containerConfig($this->container);

        $this->worker = $this->container->get(PSR7Worker::class);
        $this->pipeline = $this->createPipeline();
    }

    public function run(): void
    {
        while (true) {
            $request = $this->worker->waitRequest();
            if ($request === null) {
                break;
            }

            $response = $this->pipeline->handle($request);
            $this->worker->respond($response);
        }
    }

    private function createPipeline(): Pipeline
    {
        return new Pipeline(
            $this->container->get(RouteHandlerResolver::class),
            [
                $this->container->get(ErrorHandlerMiddleware::class),
                $this->container->get(RoutingMiddleware::class),
                $this->container->get(RequestMetricsMiddleware::class),
                $this->container->get(SessionMiddleware::class),
                $this->container->get(HttpLoggingMiddleware::class),
            ],
        );
    }
}
