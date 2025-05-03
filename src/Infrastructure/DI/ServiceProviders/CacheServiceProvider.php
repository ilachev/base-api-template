<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Infrastructure\Cache\CacheConfig;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\RoadRunnerCacheService;
use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;

/**
 * @implements ServiceProvider<object>
 */
final class CacheServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Cache config
        $container->set(
            CacheConfig::class,
            static function (): CacheConfig {
                /** @var array{
                 *     engine: string,
                 *     address: string,
                 *     default_prefix: string,
                 *     default_ttl: int,
                 *     serializer: int,
                 * } $cacheConfig
                 */
                $cacheConfig = require ProjectPath::getConfigPath('cache.php');

                return CacheConfig::fromArray($cacheConfig);
            },
        );

        // Cache service
        $container->bind(CacheService::class, RoadRunnerCacheService::class);
    }
}
