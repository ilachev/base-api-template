<?php

declare(strict_types=1);

namespace App\Application\Routing;

final readonly class RouteDefinition implements RouteDefinitionInterface
{
    public function __construct(
        private string $configPath,
    ) {}

    public function defineRoutes(RouteCollectorInterface $collector): void
    {
        /** @var array<array{method: string, path: string, handler: class-string}> $routes */
        $routes = require $this->configPath;

        foreach ($routes as $route) {
            $collector->addRoute($route['method'], $route['path'], $route['handler']);
        }
    }
}
