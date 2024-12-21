<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

final readonly class Hydrator implements HydratorInterface
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @param mixed[] $data
     * @return T
     * @throws HydratorException
     */
    public function hydrate(string $className, array $data): object
    {
        try {
            /** @var ReflectionClass<T> $reflection */
            $reflection = new ReflectionClass($className);

            $this->validateClassVisibility($reflection);

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                throw new HydratorException('Class must have a constructor');
            }

            $parameters = [];
            foreach ($constructor->getParameters() as $parameter) {
                $paramName = $parameter->getName();
                if (!array_key_exists($paramName, $data) && !$parameter->isOptional()) {
                    throw new HydratorException(
                        "Missing required constructor parameter: {$paramName}"
                    );
                }
                $parameters[] = array_key_exists($paramName, $data)
                    ? $data[$paramName]
                    : $parameter->getDefaultValue();
            }

            /** @var T */
            return $reflection->newInstanceArgs($parameters);
        } catch (ReflectionException $e) {
            throw new HydratorException(
                "Failed to create reflection for class {$className}",
                previous: $e
            );
        } catch (\TypeError $e) {
            throw new HydratorException(
                $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @param mixed $object
     * @return array<string, mixed>
     * @throws HydratorException
     */
    public function extract(mixed $object): array
    {
        if (!is_object($object)) {
            throw new HydratorException('Failed to extract data: argument must be an object');
        }

        try {
            $reflection = new ReflectionClass($object);
            $this->validateClassVisibility($reflection);

            $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
            $data = [];

            foreach ($properties as $property) {
                $data[$property->getName()] = $property->getValue($object);
            }

            return $data;
        } catch (ReflectionException $e) {
            throw new HydratorException('Failed to extract data', previous: $e);
        }
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @throws HydratorException
     */
    private function validateClassVisibility(ReflectionClass $reflection): void
    {
        $nonPublicProperties = $reflection->getProperties(
            ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED
        );

        if ($nonPublicProperties !== []) {
            $names = array_map(
                static fn(ReflectionProperty $property): string => $property->getName(),
                $nonPublicProperties
            );

            throw new HydratorException(
                sprintf(
                    'Class %s contains non-public properties: %s. Only public properties are allowed.',
                    $reflection->getName(),
                    implode(', ', $names)
                )
            );
        }
    }
}
