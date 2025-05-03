<?php

declare(strict_types=1);

namespace App\Infrastructure\DI\ServiceProviders;

use App\Application\Routing\RouteDefinition;
use App\Application\Routing\RouteDefinitionInterface;
use App\Application\Routing\RouterInterface;
use App\Infrastructure\Config\ProjectPath;
use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ServiceProvider;
use App\Infrastructure\Routing\Router;

/**
 * @implements ServiceProvider<object>
 */
final readonly class RoutingServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        // Router
        $container->bind(RouterInterface::class, Router::class);

        // Route definition
        $container->set(
            RouteDefinitionInterface::class,
            static fn() => new RouteDefinition(ProjectPath::getConfigPath('routes.php')),
        );
    }
}
