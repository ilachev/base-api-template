<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

/**
 * Adapter for hydrating Protobuf-generated objects which use protected properties with setters.
 */
final class ProtobufAdapter
{
    /**
     * Cache of property to setter method mappings.
     * Limited to 100 entries to prevent memory leaks in long-running processes (RoadRunner).
     *
     * @var array<string, array<string, string>>
     */
    private static array $propertySetterCache = [];

    /**
     * Maximum number of classes to cache to prevent memory leaks.
     */
    private const int MAX_CACHE_SIZE = 100;

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $data
     * @throws HydratorException
     */
    public function hydrate(string $className, array $data): object
    {
        if (!class_exists($className)) {
            throw new HydratorException("Class {$className} does not exist");
        }

        // Use constructor to create base object
        try {
            /** @var T $instance */
            $instance = new $className();
        } catch (\Throwable $e) {
            throw new HydratorException("Failed to instantiate class {$className}", previous: $e);
        }

        $this->mapProperties($instance, $data);

        return $instance;
    }

    /**
     * @param array<string, mixed> $data
     * @throws HydratorException
     */
    private function mapProperties(object $instance, array $data): void
    {
        $className = \get_class($instance);

        // Build setter map if not in cache
        if (!isset(self::$propertySetterCache[$className])) {
            // Limit cache size to prevent memory leaks in long-running processes
            if (\count(self::$propertySetterCache) >= self::MAX_CACHE_SIZE) {
                // Remove the first item (oldest) when we reach the limit
                reset(self::$propertySetterCache);
                $firstKey = key(self::$propertySetterCache);
                // First key can never be null in a non-empty array
                unset(self::$propertySetterCache[$firstKey]);
            }

            self::$propertySetterCache[$className] = $this->buildSetterMap($className);
        }

        $setterMap = self::$propertySetterCache[$className];

        // Apply data using available setters
        foreach ($data as $property => $value) {
            if (!isset($setterMap[$property])) {
                continue; // Skip properties without setters
            }

            $setter = $setterMap[$property];

            try {
                $instance->{$setter}($value);
            } catch (\Throwable $e) {
                throw new HydratorException(
                    "Failed to set property {$property} on {$className} using {$setter}",
                    previous: $e,
                );
            }
        }
    }

    /**
     * Build a map of property names to setter methods.
     *
     * @param class-string $className
     * @return array<string, string>
     */
    private function buildSetterMap(string $className): array
    {
        $map = [];

        // Use reflection just once to build the map
        try {
            $reflection = new \ReflectionClass($className);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $methodName = $method->getName();

                // Match setter methods (set*)
                if (str_starts_with($methodName, 'set')) {
                    $propertyName = lcfirst(substr($methodName, 3));
                    $map[$propertyName] = $methodName;
                }
            }
        } catch (\ReflectionException) {
            // Silently return empty map on reflection failure
            return [];
        }

        return $map;
    }
}
