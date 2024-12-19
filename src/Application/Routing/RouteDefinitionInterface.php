<?php

declare(strict_types=1);

namespace App\Application\Routing;

interface RouteDefinitionInterface
{
    public function defineRoutes(RouteCollectorInterface $collector): void;
}
