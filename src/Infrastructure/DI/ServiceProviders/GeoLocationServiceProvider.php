<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Application\Client\GeoLocationConfig;
use App\Application\Client\GeoLocationService;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\GeoLocation\IP2LocationGeoLocationService;
use App\Infrastructure\Logger\Logger;

/**
 * @implements ServiceProvider<object>
 */
final class GeoLocationServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // GeoLocation config
        $container->set(
            GeoLocationConfig::class,
            static function (): GeoLocationConfig {
                /** @var array{
                 *     db_path: string,
                 *     download_token: string,
                 *     download_url: string,
                 *     database_code: string,
                 *     cache_ttl: int,
                 * } $geoConfig
                 */
                $geoConfig = require ProjectPath::getConfigPath('geolocation.php');

                return GeoLocationConfig::fromArray($geoConfig);
            },
        );

        // GeoLocation service
        $container->bind(GeoLocationService::class, IP2LocationGeoLocationService::class);

        // IP2Location implementation
        $container->set(
            IP2LocationGeoLocationService::class,
            static function (Container $container): IP2LocationGeoLocationService {
                /** @var GeoLocationConfig $config */
                $config = $container->get(GeoLocationConfig::class);

                /** @var CacheService $cache */
                $cache = $container->get(CacheService::class);

                /** @var Logger $logger */
                $logger = $container->get(Logger::class);

                return new IP2LocationGeoLocationService($config, $cache, $logger);
            },
        );
    }
}
