<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Application\Handlers\HomeHandler;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;

use function FastRoute\simpleDispatcher;

final readonly class Router
{
    private Dispatcher $dispatcher;

    public function __construct()
    {
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
            $this->defineRoutes($r);
        });
    }

    private function defineRoutes(RouteCollector $r): void
    {
        $r->addRoute('GET', '/home', HomeHandler::class);
    }

    public function dispatch(ServerRequestInterface $request): RouteResult
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        /** @var array{0: int, 1?: string, 2?: array<string, string>} $routeInfo */
        return new RouteResult($routeInfo);
    }
}
