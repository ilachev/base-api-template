<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Application\Middleware\ApiStatsMiddleware;
use App\Domain\Session\SessionService;
use App\Domain\Stats\ApiStatRepository;
use App\Domain\Stats\ApiStatService;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Storage\Stats\PostgreSQLApiStatRepository;

/**
 * @implements ServiceProvider<object>
 */
final class ApiStatsServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // PostgreSQL implementation for ApiStatRepository
        $container->bind(PostgreSQLApiStatRepository::class, PostgreSQLApiStatRepository::class);

        // ApiStat Repository
        $container->set(
            ApiStatRepository::class,
            static fn(Container $container): ApiStatRepository => $container->get(PostgreSQLApiStatRepository::class),
        );

        // ApiStat Service
        $container->bind(ApiStatService::class, ApiStatService::class);

        // ApiStats Middleware
        $container->set(
            ApiStatsMiddleware::class,
            static function (Container $container): ApiStatsMiddleware {
                /** @var ApiStatService $apiStatService */
                $apiStatService = $container->get(ApiStatService::class);

                /** @var SessionService $sessionService */
                $sessionService = $container->get(SessionService::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new ApiStatsMiddleware($apiStatService, $sessionService, $logger);
            },
        );
    }
}
