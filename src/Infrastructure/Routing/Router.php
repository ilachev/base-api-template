<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing;

use App\Application\Routing\RouteDefinitionInterface;
use App\Application\Routing\RouteResult;
use App\Application\Routing\RouterInterface;
use App\Application\Routing\RouteStatus;
use Psr\Http\Message\ServerRequestInterface;

final class Router implements RouterInterface
{
    /**
     * @var array<string, array<string, array{handler: string, params: array<string, string>}>>
     */
    private array $routes = [];

    /**
     * @var array<string, array<string>>
     */
    private array $methodsByPath = [];

    public function __construct(private readonly RouteDefinitionInterface $routeDefinition)
    {
        $collector = new DefaultRouteCollector();
        $this->routeDefinition->defineRoutes($collector);
        $this->buildRouteMap($collector->getRoutes());
    }

    public function dispatch(ServerRequestInterface $request): RouteResult
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Check for exact path match
        if (isset($this->routes[$method][$path])) {
            return new RouteResult(
                status: RouteStatus::FOUND,
                handler: $this->routes[$method][$path]['handler'],
                params: $this->routes[$method][$path]['params'],
            );
        }

        // Check for trailing slash match
        if (str_ends_with($path, '/') && isset($this->routes[$method][rtrim($path, '/')])) {
            return new RouteResult(
                status: RouteStatus::FOUND,
                handler: $this->routes[$method][rtrim($path, '/')]['handler'],
                params: $this->routes[$method][rtrim($path, '/')]['params'],
            );
        }

        // If path exists but method doesn't match
        if ($this->pathExists($path)) {
            return new RouteResult(
                status: RouteStatus::METHOD_NOT_ALLOWED,
                handler: implode(',', $this->getAllowedMethods($path)),
            );
        }

        // Check for parameterized paths
        foreach ($this->routes[$method] ?? [] as $routePath => $routeData) {
            $params = $this->matchPath($routePath, $path);
            if ($params !== null) {
                return new RouteResult(
                    status: RouteStatus::FOUND,
                    handler: $routeData['handler'],
                    params: array_merge($routeData['params'], $params),
                );
            }
        }

        return new RouteResult(status: RouteStatus::NOT_FOUND);
    }

    /**
     * @param array<array{method: string, path: string, handler: string}> $routes
     */
    private function buildRouteMap(array $routes): void
    {
        foreach ($routes as $route) {
            $method = $route['method'];
            $path = $route['path'];
            $handler = $route['handler'];

            // Check for parameters in path
            $params = [];
            $pathPattern = $path;

            // Process parameters in {name} format
            if (str_contains($path, '{') && str_contains($path, '}')) {
                $pathPattern = preg_replace('/{([^}]+)}/', '([^/]+)', $path);
            }

            $this->routes[$method][$path] = [
                'handler' => $handler,
                'params' => $params,
            ];

            // Store available methods for each path
            $this->methodsByPath[$path][] = $method;
        }
    }

    /**
     * Checks if path exists in the router.
     */
    private function pathExists(string $path): bool
    {
        return isset($this->methodsByPath[$path]) || isset($this->methodsByPath[rtrim($path, '/')]);
    }

    /**
     * Returns all allowed methods for the specified path.
     *
     * @return array<string>
     */
    private function getAllowedMethods(string $path): array
    {
        if (isset($this->methodsByPath[$path])) {
            return $this->methodsByPath[$path];
        }

        if (str_ends_with($path, '/') && isset($this->methodsByPath[rtrim($path, '/')])) {
            return $this->methodsByPath[rtrim($path, '/')];
        }

        return [];
    }

    /**
     * Checks if path matches a route pattern and extracts parameters.
     *
     * @return array<string, string>|null
     */
    private function matchPath(string $routePath, string $requestPath): ?array
    {
        // Simple comparison for paths without parameters
        if ($routePath === $requestPath) {
            return [];
        }

        // Check for parameters in the path
        if (!str_contains($routePath, '{') || !str_contains($routePath, '}')) {
            return null;
        }

        // Extract parameter names
        $patternMatches = [];
        preg_match_all('/{([^}]+)}/', $routePath, $patternMatches);
        $paramNames = $patternMatches[1];

        // Convert pattern to regex
        $pattern = preg_replace('/{([^}]+)}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        // Perform matching
        $matches = [];
        if (preg_match($pattern, $requestPath, $matches)) {
            // Form parameters array
            $params = [];
            array_shift($matches); // Remove full match

            foreach ($paramNames as $index => $name) {
                // $index+1 because array_shift removed the first element
                if ($index < \count($matches)) {
                    $params[$name] = $matches[$index];
                }
            }

            return $params;
        }

        return null;
    }
}
