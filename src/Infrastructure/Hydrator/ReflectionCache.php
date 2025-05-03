<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

/**
 * Кеширование рефлексии классов для оптимизации производительности.
 */
interface ReflectionCache
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @return \ReflectionClass<T>
     * @throws \ReflectionException
     */
    public function getReflectionClass(string $className): \ReflectionClass;

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return array<\ReflectionParameter>
     * @throws \ReflectionException
     * @throws HydratorException
     */
    public function getConstructorParams(string $className): array;

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return array<\ReflectionProperty>
     * @throws \ReflectionException
     */
    public function getPublicProperties(string $className): array;

    /**
     * @param class-string $className
     */
    public function isProtobufMessage(string $className): bool;
}
