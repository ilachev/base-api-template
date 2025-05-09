<?php

declare(strict_types=1);

namespace ProtoPhpGen\Parser;

use ProtoPhpGen\Config\StandaloneConfig;
use ProtoPhpGen\Model\ClassMapping;

/**
 * Scanner for finding domain classes with proto mapping attributes.
 */
final class DomainClassScanner
{
    private AttributeParser $attributeParser;

    public function __construct(
        private StandaloneConfig $config,
    ) {
        $this->attributeParser = new AttributeParser();
    }

    /**
     * Scan directory for domain classes with proto mapping.
     * 
     * @return array<int, ClassMapping>
     */
    public function scan(): array
    {
        $mappings = [];
        $files = $this->findPhpFiles($this->config->getDomainDir());
        
        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className === null) {
                continue;
            }
            
            $fullClassName = $this->config->getDomainNamespace() . '\\' . $className;
            
            if (!class_exists($fullClassName)) {
                require_once $file;
            }
            
            if (!class_exists($fullClassName)) {
                continue;
            }
            
            $mapping = $this->attributeParser->parse($fullClassName);
            if ($mapping !== null) {
                $mappings[] = $mapping;
            }
        }
        
        return $mappings;
    }

    /**
     * Find all PHP files in a directory recursively.
     * 
     * @param string $dir Directory to scan
     * @return array<int, string> List of PHP files
     */
    private function findPhpFiles(string $dir): array
    {
        $result = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );
        
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $result[] = $file->getPathname();
            }
        }
        
        return $result;
    }

    /**
     * Extract class name from file path.
     * 
     * @param string $file File path
     * @return string|null Class name
     */
    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }
        
        // Get namespace
        $namespaceMatches = [];
        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
        
        if (empty($namespaceMatches)) {
            return null;
        }
        
        $namespace = $namespaceMatches[1];
        
        // Get class name
        $classMatches = [];
        preg_match('/class\s+([^\s{]+)/', $content, $classMatches);
        
        if (empty($classMatches)) {
            return null;
        }
        
        $className = $classMatches[1];
        
        // Remove namespace prefix if it matches the domain namespace
        $domainNamespace = $this->config->getDomainNamespace();
        if (strpos($namespace, $domainNamespace) === 0) {
            $relativePath = substr($namespace, strlen($domainNamespace));
            return trim($relativePath, '\\') . '\\' . $className;
        }
        
        return null;
    }
}
