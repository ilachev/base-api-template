<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Application\Routing\RouteDefinitionInterface;
use App\Application\Routing\RouteResult;
use App\Application\Routing\RouterInterface;
use App\Application\Routing\RouteStatus;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;
use function FastRoute\simpleDispatcher;

final readonly class FastRouteAdapter implements RouterInterface
{
    private Dispatcher $dispatcher;

    public function __construct(private RouteDefinitionInterface $routeDefinition)
    {
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r): void {
            $this->routeDefinition->defineRoutes(new FastRouteCollector($r));
        });
    }

    public function dispatch(ServerRequestInterface $request): RouteResult
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath(),
        );

        /** @var array{0: int, 1?: string|array<int, string>, 2?: array<string, string>} $routeInfo */
        $status = match ($routeInfo[0]) {
            Dispatcher::FOUND => RouteStatus::FOUND,
            Dispatcher::METHOD_NOT_ALLOWED => RouteStatus::METHOD_NOT_ALLOWED,
            default => RouteStatus::NOT_FOUND,
        };

        // Обработка случая, когда handler является массивом (для METHOD_NOT_ALLOWED)
        $handler = $routeInfo[1] ?? null;
        if (\is_array($handler)) {
            $handler = implode(',', $handler);
        }

        return new RouteResult(
            status: $status,
            handler: $handler,
            params: $routeInfo[2] ?? [],
        );
    }
}
