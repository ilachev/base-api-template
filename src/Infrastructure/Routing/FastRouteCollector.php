<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Application\Routing\RouteCollectorInterface;
use FastRoute\RouteCollector;

final readonly class FastRouteCollector implements RouteCollectorInterface
{
    public function __construct(
        private RouteCollector $collector,
    ) {}

    public function addRoute(string $method, string $path, string $handler): void
    {
        $this->collector->addRoute($method, $path, $handler);
    }
}
