<?php

declare(strict_types=1);

namespace App\Infrastructure\Hydrator;

use Google\Protobuf\Internal\Message;

/**
 * Cache for reflection data using LRU (Least Recently Used) strategy.
 * This implementation provides better memory management for long-running processes
 * by evicting least recently used items when cache reaches its capacity.
 */
final class LRUReflectionCache implements ReflectionCache
{
    /**
     * Maximum number of entries in each cache to prevent memory leaks.
     */
    private int $maxCacheSize;

    /**
     * Combined cache structure for each class that holds all reflection data
     * This avoids inconsistency problems where a class exists in one cache but not another.
     *
     * @var array<string, array{
     *     reflection: \ReflectionClass<object>,
     *     constructorParams: array<\ReflectionParameter>,
     *     properties: array<\ReflectionProperty>,
     *     isProtobuf: bool,
     *     lastAccessed: int
     * }>
     */
    private array $cache = [];

    /**
     * A sorted list of class names by lastAccessed timestamp to quickly determine LRU item.
     *
     * @var array<string, int>
     */
    private array $accessOrder = [];

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
        if (!isset($this->cache[$className])) {
            $this->initializeCache($className);
        } else {
            $this->updateAccessTime($className);
        }

        /** @var \ReflectionClass<T> */
        return $this->cache[$className]['reflection'];
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
        if (!isset($this->cache[$className])) {
            $this->initializeCache($className);
        } else {
            $this->updateAccessTime($className);
        }

        // If constructorParams array is empty, check if class has constructor
        if (empty($this->cache[$className]['constructorParams'])) {
            $reflection = $this->cache[$className]['reflection'];
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                throw new HydratorException("Class {$className} must have a constructor");
            }
        }

        return $this->cache[$className]['constructorParams'];
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return array<\ReflectionProperty>
     * @throws \ReflectionException
     */
    public function getPublicProperties(string $className): array
    {
        if (!isset($this->cache[$className])) {
            $this->initializeCache($className);
        } else {
            $this->updateAccessTime($className);
        }

        return $this->cache[$className]['properties'];
    }

    /**
     * @param class-string $className
     */
    public function isProtobufMessage(string $className): bool
    {
        if (!$this->classExists($className)) {
            return false;
        }

        if (!isset($this->cache[$className])) {
            $this->initializeCache($className);
        } else {
            $this->updateAccessTime($className);
        }

        return $this->cache[$className]['isProtobuf'];
    }

    /**
     * Initialize cache entry for a class.
     *
     * @template T of object
     * @param class-string<T> $className
     * @throws \ReflectionException
     */
    private function initializeCache(string $className): void
    {
        // Check if we need to evict an item before adding a new one
        if (\count($this->cache) >= $this->maxCacheSize) {
            $this->evictLeastRecentlyUsed();
        }

        /** @var \ReflectionClass<T> $reflection */
        $reflection = new \ReflectionClass($className);

        // Get constructor params immediately to match type signature
        $constructorParams = [];
        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            $constructorParams = $constructor->getParameters();
        }

        $this->cache[$className] = [
            'reflection' => $reflection,
            'constructorParams' => $constructorParams,
            'properties' => $reflection->getProperties(\ReflectionProperty::IS_PUBLIC),
            'isProtobuf' => class_exists($className) && is_subclass_of($className, Message::class),
            'lastAccessed' => $this->getCurrentTime(),
        ];

        $this->accessOrder[$className] = $this->cache[$className]['lastAccessed'];
    }

    /**
     * Update the access time for a class.
     */
    private function updateAccessTime(string $className): void
    {
        $currentTime = $this->getCurrentTime();

        // Remove old access time
        unset($this->accessOrder[$className]);

        // Update access time in cache
        $this->cache[$className]['lastAccessed'] = $currentTime;

        // Add updated access time to order tracking
        $this->accessOrder[$className] = $currentTime;
    }

    /**
     * Find and remove the least recently used cache entry.
     */
    private function evictLeastRecentlyUsed(): void
    {
        if (empty($this->accessOrder)) {
            return;
        }

        // Find the class with the oldest access time
        asort($this->accessOrder);
        $oldestClass = key($this->accessOrder);

        // Remove from both cache structures
        unset($this->cache[$oldestClass], $this->accessOrder[$oldestClass]);

    }

    /**
     * Get current timestamp for tracking access order
     * Extracted to method for easier testing.
     */
    private function getCurrentTime(): int
    {
        return (int) microtime(true) * 1000;
    }

    /**
     * Check if a class exists
     * Extracted to method for easier testing.
     *
     * @param class-string $className
     */
    private function classExists(string $className): bool
    {
        return class_exists($className);
    }

    /**
     * Clear the cache completely
     * Useful for testing or to free memory in long-running processes.
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->accessOrder = [];
    }

    /**
     * Get current cache size
     * Useful for diagnostics.
     */
    public function getSize(): int
    {
        return \count($this->cache);
    }
}
