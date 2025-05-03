<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

final readonly class ReflectionHydrator implements Hydrator
{
    public function __construct(
        private ReflectionCache $cache,
        private ProtobufHydration $protobufHydration,
    ) {}

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param mixed[] $data
     * @return T
     * @throws HydratorException
     */
    public function hydrate(string $className, array $data): object
    {
        if ($this->cache->isProtobufMessage($className)) {
            /** @var array<string, mixed> $typedData */
            $typedData = $data;

            /** @var T */
            return $this->protobufHydration->hydrate($className, $typedData);
        }

        try {
            $reflection = $this->cache->getReflectionClass($className);
            $this->validateClassVisibility($reflection);

            $constructorParams = $this->cache->getConstructorParams($className);
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
            $reflection = $this->cache->getReflectionClass($className);
            $this->validateClassVisibility($reflection);

            $properties = $this->cache->getPublicProperties($className);
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
