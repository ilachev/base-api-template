<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Application\Routing\RouteCollectorInterface;

final class DefaultRouteCollector implements RouteCollectorInterface
{
    /**
     * @var array<array{method: string, path: string, handler: string}>
     */
    private array $routes = [];

    public function addRoute(string $method, string $path, string $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }

    /**
     * @return array<array{method: string, path: string, handler: string}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
