<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Application\Handlers\HomeHandler;

final readonly class RouteDefinition implements RouteDefinitionInterface
{
    public function defineRoutes(RouteCollectorInterface $collector): void
    {
        $collector->addRoute('GET', '/home', HomeHandler::class);
    }
}
