<?php

declare(strict_types=1);

namespace App\Infrastructure\Routing\Generator;

final readonly class ProtoRouteProvider implements RouteProvider
{
    /**
     * @param string $protoDir Directory containing .proto files
     * @param array<string, string> $handlerMapping Mapping of service.method => handler
     */
    public function __construct(
        private string $protoDir,
        private array $handlerMapping = [],
    ) {
    }
    
    /**
     * @return array<array{
     *     method: string,
     *     path: string,
     *     handler: string,
     *     operation_id?: string
     * }>
     */
    public function getRoutes(): array
    {
        $routes = [];
        $protoFiles = $this->findProtoFiles();
        
        foreach ($protoFiles as $protoFile) {
            $fileContent = file_get_contents($protoFile);
            if ($fileContent === false) {
                continue;
            }
            
            $this->extractRoutesFromProtoContent($fileContent, $routes);
        }
        
        return $routes;
    }
    
    /**
     * @return array<string>
     */
    private function findProtoFiles(): array
    {
        // Try simple glob first
        $files = glob("{$this->protoDir}/*.proto");
        if ($files === false || empty($files)) {
            // Try recursive glob
            $files = $this->globRecursive("{$this->protoDir}/*.proto");
        }
        
        return $files;
    }
    
    /**
     * Recursive glob function that works reliably on different systems
     * 
     * @param string $pattern
     * @param int $flags
     * @return array<string>
     */
    private function globRecursive(string $pattern, int $flags = 0): array
    {
        $files = glob($pattern, $flags);
        $files = $files !== false ? $files : [];
        
        $dirs = glob(dirname($pattern) . '/*', GLOB_ONLYDIR|GLOB_NOSORT);
        if ($dirs === false) {
            return $files;
        }
        
        foreach ($dirs as $dir) {
            $moreFiles = $this->globRecursive($dir . '/' . basename($pattern), $flags);
            $files = array_merge($files, $moreFiles);
        }
        
        return $files;
    }
    
    /**
     * @param string $content
     * @param array<array{method: string, path: string, handler: string, operation_id?: string}> &$routes
     */
    private function extractRoutesFromProtoContent(string $content, array &$routes): void
    {
        // Extract service name
        preg_match('/service\s+(\w+)\s*{/m', $content, $serviceMatches);
        $serviceName = $serviceMatches[1] ?? '';
        
        if (empty($serviceName)) {
            return;
        }
        
        // More relaxed pattern to find HTTP annotations
        if (preg_match_all('/option\s*\(\s*google\.api\.http\s*\)\s*=\s*\{\s*([^}]+)\s*\}/s', $content, $httpOptions)) {
            foreach ($httpOptions[0] as $index => $fullMatch) {
                // Extract the method name by looking 100 chars before the option
                $position = strpos($content, $fullMatch);
                $start = max(0, $position - 100);
                $chunk = substr($content, $start, $position - $start);
                
                preg_match('/rpc\s+(\w+)\s*\(/s', $chunk, $methodMatch);
                $methodName = $methodMatch[1] ?? 'unknown';
                
                // Parse HTTP method and path
                $httpConfig = $httpOptions[1][$index];
                
                foreach (['get', 'post', 'put', 'delete', 'patch'] as $httpMethod) {
                    if (preg_match('/' . $httpMethod . '\s*:\s*"([^"]+)"/', $httpConfig, $methodMatches)) {
                        $path = $methodMatches[1];
                        
                        $operationId = "{$serviceName}.{$methodName}";
                        $handler = $this->resolveHandler($serviceName, $methodName);
                        
                        $routes[] = [
                            'method' => strtoupper($httpMethod),
                            'path' => $path,
                            'handler' => $handler,
                            'operation_id' => $operationId,
                        ];
                        
                        break;
                    }
                }
            }
        }
    }
    
    private function resolveHandler(string $serviceName, string $methodName): string
    {
        // Check explicit mapping
        $key = "{$serviceName}.{$methodName}";
        if (isset($this->handlerMapping[$key])) {
            return $this->handlerMapping[$key];
        }
        
        // Apply naming convention: HomeService::Home -> HomeHandler
        $handlerName = str_replace('Service', 'Handler', $serviceName);
        return "App\\Application\\Handlers\\{$handlerName}";
    }
}