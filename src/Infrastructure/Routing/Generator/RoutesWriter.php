<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing\Generator;

final readonly class RoutesWriter
{
    /**
     * @param RouteProvider $provider Routes provider
     * @param string $outputFile Path to the routes configuration file
     */
    public function __construct(
        private RouteProvider $provider,
        private string $outputFile,
    ) {}

    public function generateRoutesFile(): void
    {
        $routes = $this->provider->getRoutes();
        $content = $this->generateFileContent($routes);

        file_put_contents($this->outputFile, $content);
    }

    /**
     * @param array<array{
     *     method: string,
     *     path: string,
     *     handler: string,
     *     operation_id?: string
     * }> $routes
     */
    private function generateFileContent(array $routes): string
    {
        $routesCode = [];
        $imports = [
            'App\Application\Handlers\HandlerInterface',
        ];

        foreach ($routes as $route) {
            // Extract the handler class name
            preg_match('/^(.+)::class$/', $route['handler'], $matches);
            if (isset($matches[1])) {
                $handlerClass = $matches[1];
                $imports[] = $handlerClass;

                // Replace full namespace with short class name for the handler
                $className = substr($handlerClass, strrpos($handlerClass, '\\') + 1);
                $route['handler'] = $className;
            }

            $comment = isset($route['operation_id']) ? "    // {$route['operation_id']}\n" : '';
            $routesCode[] = "{$comment}    [
        'method' => '{$route['method']}',
        'path' => '{$route['path']}',
        'handler' => {$route['handler']}::class,
    ]";
        }

        $routesStr = implode(",\n", $routesCode);

        // Remove duplicates and sort imports
        $imports = array_unique($imports);
        sort($imports);

        // Generate use statements
        $useStatements = array_map(static fn($class) => "use {$class};", $imports);
        $useStatementsStr = implode("\n", $useStatements);

        return <<<PHP
            <?php

            declare(strict_types=1);

            {$useStatementsStr}

            /**
             * WARNING: This file is automatically generated
             * from protobuf definitions. Do not edit manually.
             *
             * @return array<array{
             *     method: string,
             *     path: string,
             *     handler: class-string<HandlerInterface>
             * }>
             */
            return [
            {$routesStr}
            ];

            PHP;
    }
}
