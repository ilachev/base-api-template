<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use Google\Protobuf\Internal\Message;

/**
 * Кеш рефлексии с ограниченным размером хранения.
 */
final class LimitedReflectionCache implements ReflectionCache
{
    /** @var array<string, \ReflectionClass<object>> */
    private array $reflectionCache = [];

    /** @var array<string, array<\ReflectionParameter>> */
    private array $constructorParamsCache = [];

    /** @var array<string, array<\ReflectionProperty>> */
    private array $propertiesCache = [];

    /** @var array<string, bool> */
    private array $protobufCache = [];

    private int $maxCacheSize;

    public function __construct(int $maxCacheSize = 100)
    {
        $this->maxCacheSize = $maxCacheSize;
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return \ReflectionClass<T>
     * @throws \ReflectionException
     */
    public function getReflectionClass(string $className): \ReflectionClass
    {
        if (!isset($this->reflectionCache[$className])) {
            /** @var \ReflectionClass<T> $reflection */
            $reflection = new \ReflectionClass($className);
            $this->manageCache($this->reflectionCache, $className, $reflection);
        }

        /** @var \ReflectionClass<T> */
        return $this->reflectionCache[$className];
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return array<\ReflectionParameter>
     * @throws \ReflectionException
     * @throws HydratorException
     */
    public function getConstructorParams(string $className): array
    {
        if (!isset($this->constructorParamsCache[$className])) {
            $reflection = $this->getReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                throw new HydratorException('Class must have a constructor');
            }

            $params = $constructor->getParameters();
            $this->manageCache($this->constructorParamsCache, $className, $params);
        }

        return $this->constructorParamsCache[$className];
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return array<\ReflectionProperty>
     * @throws \ReflectionException
     */
    public function getPublicProperties(string $className): array
    {
        if (!isset($this->propertiesCache[$className])) {
            $reflection = $this->getReflectionClass($className);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
            $this->manageCache($this->propertiesCache, $className, $properties);
        }

        return $this->propertiesCache[$className];
    }

    /**
     * Проверяет существование класса
     * Выделен в отдельный метод для возможности мокирования в тестах.
     *
     * @param class-string $className
     */
    private function isClassExists(string $className): bool
    {
        return class_exists($className);
    }

    /**
     * @param class-string $className
     */
    public function isProtobufMessage(string $className): bool
    {
        if (!$this->isClassExists($className)) {
            return false;
        }

        if (isset($this->protobufCache[$className])) {
            return $this->protobufCache[$className];
        }

        $isProtobuf = is_subclass_of($className, Message::class);
        $this->manageCache($this->protobufCache, $className, $isProtobuf);

        return $isProtobuf;
    }

    /**
     * @template T
     * @param array<string, T> $cache
     * @param T $value
     */
    private function manageCache(array &$cache, string $key, $value): void
    {
        if (\count($cache) >= $this->maxCacheSize) {
            reset($cache);
            $firstKey = key($cache);
            unset($cache[$firstKey]);
        }

        $cache[$key] = $value;
    }
}
