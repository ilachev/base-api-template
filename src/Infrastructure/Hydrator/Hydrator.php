<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use Google\Protobuf\Internal\Message;

final class Hydrator implements HydratorInterface
{
    /**
     * Maximum number of constructor parameters to cache.
     */
    private const int MAX_CONSTRUCTOR_PARAMS_CACHE_SIZE = 100;

    /**
     * Cache for constructor parameters by class name.
     *
     * @var array<string, array<\ReflectionParameter>>
     */
    private static array $constructorParamsCache = [];

    /**
     * Cache for class reflection objects.
     *
     * @var array<string, \ReflectionClass<object>>
     */
    private static array $reflectionCache = [];

    /**
     * Cache for public properties by class.
     *
     * @var array<string, array<\ReflectionProperty>>
     */
    private static array $propertiesCache = [];

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param mixed[] $data
     * @return T
     * @throws HydratorException
     */
    public function hydrate(string $className, array $data): object
    {
        // Check if this is a Protobuf object (has Message in inheritance chain)
        if ($this->isProtobufMessage($className)) {
            /** @var array<string, mixed> $typedData */
            $typedData = $data;

            return $this->hydrateProtobuf($className, $typedData);
        }

        // Standard hydration for regular objects with public properties
        try {
            // Get cached reflection if available
            if (isset(self::$reflectionCache[$className])) {
                /** @var \ReflectionClass<object> */
                $reflection = self::$reflectionCache[$className];
            } else {
                $reflection = new \ReflectionClass($className);
                // Limit cache size
                if (\count(self::$reflectionCache) >= self::MAX_CONSTRUCTOR_PARAMS_CACHE_SIZE) {
                    // Discard oldest entry
                    reset(self::$reflectionCache);
                    $firstKey = key(self::$reflectionCache);
                    // First key can never be null in a non-empty array
                    unset(self::$reflectionCache[$firstKey]);
                }
                self::$reflectionCache[$className] = $reflection;
            }

            $this->validateClassVisibility($reflection);

            // Get constructor parameters from cache if available
            if (isset(self::$constructorParamsCache[$className])) {
                /** @var array<\ReflectionParameter> */
                $constructorParams = self::$constructorParamsCache[$className];
            } else {
                $constructor = $reflection->getConstructor();
                if ($constructor === null) {
                    throw new HydratorException('Class must have a constructor');
                }
                $constructorParams = $constructor->getParameters();

                // Limit cache size
                if (\count(self::$constructorParamsCache) >= self::MAX_CONSTRUCTOR_PARAMS_CACHE_SIZE) {
                    // Discard oldest entry
                    reset(self::$constructorParamsCache);
                    $firstKey = key(self::$constructorParamsCache);
                    // First key can never be null in a non-empty array
                    unset(self::$constructorParamsCache[$firstKey]);
                }
                self::$constructorParamsCache[$className] = $constructorParams;
            }

            $parameters = [];
            foreach ($constructorParams as $parameter) {
                $paramName = $parameter->getName();
                if (!\array_key_exists($paramName, $data) && !$parameter->isOptional()) {
                    throw new HydratorException(
                        "Missing required constructor parameter: {$paramName}",
                    );
                }
                $parameters[] = \array_key_exists($paramName, $data)
                    ? $data[$paramName]
                    : $parameter->getDefaultValue();
            }

            /** @var T */
            return $reflection->newInstanceArgs($parameters);
        } catch (\ReflectionException $e) {
            throw new HydratorException(
                "Failed to create reflection for class {$className}",
                previous: $e,
            );
        } catch (\TypeError $e) {
            throw new HydratorException(
                $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Maximum number of class inheritance lookups to cache.
     */
    private const int MAX_INHERITANCE_CACHE_SIZE = 100;

    /**
     * Checks if a class is a Protobuf Message.
     *
     * @param class-string $className
     */
    private function isProtobufMessage(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        /** @var array<string, bool> $cache */
        static $cache = [];

        if (isset($cache[$className])) {
            return $cache[$className];
        }

        // Prevent unlimited growth of the cache in long-running processes
        if (\count($cache) >= self::MAX_INHERITANCE_CACHE_SIZE) {
            // Discard oldest entry
            reset($cache);
            $firstKey = key($cache);
            // First key can never be null in a non-empty array
            unset($cache[$firstKey]);
        }

        $isProtobuf = is_subclass_of($className, Message::class);
        $cache[$className] = $isProtobuf;

        return $isProtobuf;
    }

    /**
     * Hydrates a Protobuf message using its setter methods.
     *
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $data
     * @return T
     */
    private function hydrateProtobuf(string $className, array $data): object
    {
        /** @var ProtobufAdapter|null $adapter */
        static $adapter = null;

        if ($adapter === null) {
            $adapter = new ProtobufAdapter();
        }

        /** @var T */
        return $adapter->hydrate($className, $data);
    }

    /**
     * @return array<string, mixed>
     * @throws HydratorException
     */
    public function extract(mixed $object): array
    {
        if (!\is_object($object)) {
            throw new HydratorException('Failed to extract data: argument must be an object');
        }

        $className = $object::class;

        try {
            // Use cached reflection if available
            if (isset(self::$reflectionCache[$className])) {
                /** @var \ReflectionClass<object> */
                $reflection = self::$reflectionCache[$className];
            } else {
                $reflection = new \ReflectionClass($object);

                // Limit cache size
                if (\count(self::$reflectionCache) >= self::MAX_CONSTRUCTOR_PARAMS_CACHE_SIZE) {
                    // Discard oldest entry
                    reset(self::$reflectionCache);
                    $firstKey = key(self::$reflectionCache);
                    // First key can never be null in a non-empty array
                    unset(self::$reflectionCache[$firstKey]);
                }

                self::$reflectionCache[$className] = $reflection;
            }

            $this->validateClassVisibility($reflection);

            // Use cached properties if available
            if (isset(self::$propertiesCache[$className])) {
                /** @var array<\ReflectionProperty> */
                $properties = self::$propertiesCache[$className];
            } else {
                $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

                // Limit cache size
                if (\count(self::$propertiesCache) >= self::MAX_CONSTRUCTOR_PARAMS_CACHE_SIZE) {
                    // Discard oldest entry
                    reset(self::$propertiesCache);
                    $firstKey = key(self::$propertiesCache);
                    // First key can never be null in a non-empty array
                    unset(self::$propertiesCache[$firstKey]);
                }

                self::$propertiesCache[$className] = $properties;
            }

            $data = [];

            foreach ($properties as $property) {
                $data[$property->getName()] = $property->getValue($object);
            }

            return $data;
        } catch (\ReflectionException $e) {
            throw new HydratorException('Failed to extract data', previous: $e);
        }
    }

    /**
     * @template T of object
     * @param \ReflectionClass<T> $reflection
     * @throws HydratorException
     */
    private function validateClassVisibility(\ReflectionClass $reflection): void
    {
        $nonPublicProperties = $reflection->getProperties(
            \ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED,
        );

        if ($nonPublicProperties !== []) {
            $names = array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $nonPublicProperties,
            );

            throw new HydratorException(
                \sprintf(
                    'Class %s contains non-public properties: %s. Only public properties are allowed.',
                    $reflection->getName(),
                    implode(', ', $names),
                ),
            );
        }
    }
}
