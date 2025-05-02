<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\Http\RouteHandlerResolver;
use App\Application\Middleware\{
    ApiStatsMiddleware,
    ErrorHandlerMiddleware,
    HttpLoggingMiddleware,
    Pipeline,
    RequestMetricsMiddleware,
    RoutingMiddleware,
    SessionMiddleware
};
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\DI\Container;
use App\Infrastructure\Logger\Logger;
use Spiral\RoadRunner\Http\PSR7Worker;

/** @template T of object */
final readonly class App
{
    /** @var Container<T> */
    private Container $container;

    private PSR7Worker $worker;

    private Pipeline $pipeline;

    /**
     * @return Container<T>
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

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
        // Очищаем весь кеш при запуске приложения
        $this->clearAllCache();

        while (true) {
            $request = $this->worker->waitRequest();
            if ($request === null) {
                break;
            }

            $response = $this->pipeline->handle($request);
            $this->worker->respond($response);
        }
    }

    /**
     * Очищает весь кеш при запуске приложения.
     * Использует улучшенный механизм защиты от race condition и повторяет попытки при сбоях.
     */
    private function clearAllCache(): void
    {
        $cacheService = $this->container->get(CacheService::class);
        $logger = $this->container->get(Logger::class);

        try {
            // Очищаем кеш, используя улучшенный механизм в RoadRunnerCacheService
            $success = $cacheService->clear();

            if ($success) {
                $logger->info('Cache fully cleared on application startup');
            } else {
                // Если очистка не удалась, но не выбросила исключение
                $logger->warning('Cache clearing reported failure on startup without exception');
            }
        } catch (\Throwable $e) {
            $logger->error('Failed to clear cache on startup', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function createPipeline(): Pipeline
    {
        return new Pipeline(
            $this->container->get(RouteHandlerResolver::class),
            [
                $this->container->get(ErrorHandlerMiddleware::class),
                $this->container->get(RequestMetricsMiddleware::class),
                $this->container->get(SessionMiddleware::class),
                $this->container->get(ApiStatsMiddleware::class),
                $this->container->get(RoutingMiddleware::class),
                $this->container->get(HttpLoggingMiddleware::class),
            ],
        );
    }
}
