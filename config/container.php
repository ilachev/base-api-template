<?php

declare(strict_types=1);

use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProviders\ApiStatsServiceProvider;
use App\Infrastructure\DI\ServiceProviders\ApplicationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\CacheServiceProvider;
use App\Infrastructure\DI\ServiceProviders\ClientServiceProvider;
use App\Infrastructure\DI\ServiceProviders\CoreServiceProvider;
use App\Infrastructure\DI\ServiceProviders\GeoLocationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\MigrationServiceProvider;
use App\Infrastructure\DI\ServiceProviders\RoutingServiceProvider;
use App\Infrastructure\DI\ServiceProviders\SessionServiceProvider;
use App\Infrastructure\DI\ServiceProviders\StorageServiceProvider;

return static function (Container $container): void {
    // Register all service providers
    $serviceProviders = [
        new CoreServiceProvider(),
        new CacheServiceProvider(),
        new StorageServiceProvider(),
        new SessionServiceProvider(),
        new ApiStatsServiceProvider(),
        new ClientServiceProvider(),
        new GeoLocationServiceProvider(),
        new MigrationServiceProvider(),
        new RoutingServiceProvider(),
        new ApplicationServiceProvider(),
    ];

    // Register services from each provider
    foreach ($serviceProviders as $provider) {
        $provider->register($container);
    }

    // Set container reference
    $container->set(Container::class, static fn() => $container);
};
