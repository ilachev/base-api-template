<?php

declare(strict_types=1);

namespace App\Application\Routing;

interface RouteCollectorInterface
{
    public function addRoute(string $method, string $path, string $handler): void;
}
