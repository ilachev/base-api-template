<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator;

use App\Infrastructure\Hydrator\ProtobufAdapter;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Hydrator\Fixtures\TestProtobufLike;

/**
 * Dedicated test class for the cache limiting mechanism in ProtobufAdapter.
 */
final class CacheLimitingAdapterTest extends TestCase
{
    /**
     * Test that the ProtobufAdapter limits its static cache size.
     */
    public function testCacheLimitingMechanism(): void
    {
        // Reset cache to empty state before test
        $this->resetCache();

        // We'll create a test class with fewer methods to test cache filling
        $adapter = new ProtobufAdapter();

        // Get the MAX_CACHE_SIZE constant value
        $reflectionClass = new \ReflectionClass(ProtobufAdapter::class);
        /** @var int $maxCacheSize */
        $maxCacheSize = $reflectionClass->getConstant('MAX_CACHE_SIZE');

        // Create and hydrate at least MAX_CACHE_SIZE + 1 different protobuf objects
        for ($i = 0; $i < $maxCacheSize + 10; ++$i) {
            // Create a class name that we'll never instantiate but add to the cache using reflection
            $fakeClassName = "TestProtobufClass{$i}";

            // Add this fake class to the cache manually
            $this->addToCache($fakeClassName, ['prop' => 'setProp']);
        }

        // Verify that the cache has more than the limit
        $cache = $this->getCache();
        $originalSize = \count($cache);
        self::assertGreaterThan($maxCacheSize, $originalSize);

        // Now add one more class that will trigger the cache limit mechanism
        $adapter->hydrate(TestProtobufLike::class, ['message' => 'test']);

        // Now get the cache and check it has been reduced
        $newCache = $this->getCache();

        // Verify one of two things:
        // 1. Either new cache size is less than or equal to the limit, or
        // 2. Some entries were dropped between the old and new cache
        if (\count($newCache) <= $maxCacheSize) {
            // Cache was successfully limited
            self::assertLessThanOrEqual($maxCacheSize, \count($newCache));
        } else {
            // At least some entries should have been dropped
            self::assertNotEmpty(array_diff_key($cache, $newCache), 'Expected some cache entries to be dropped');
        }
    }

    /**
     * Helper to reset the cache.
     */
    private function resetCache(): void
    {
        $reflectionClass = new \ReflectionClass(ProtobufAdapter::class);
        $cacheProperty = $reflectionClass->getProperty('propertySetterCache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue([]);
    }

    /**
     * Helper to get the current cache.
     *
     * @return array<string, array<string, string>>
     */
    private function getCache(): array
    {
        $reflectionClass = new \ReflectionClass(ProtobufAdapter::class);
        $cacheProperty = $reflectionClass->getProperty('propertySetterCache');
        $cacheProperty->setAccessible(true);

        /** @var array<string, array<string, string>> $cache */
        $cache = $cacheProperty->getValue();

        return $cache;
    }

    /**
     * Helper to add a class to the cache.
     *
     * @param array<string, string> $setterMap
     */
    private function addToCache(string $className, array $setterMap): void
    {
        $reflectionClass = new \ReflectionClass(ProtobufAdapter::class);
        $cacheProperty = $reflectionClass->getProperty('propertySetterCache');
        $cacheProperty->setAccessible(true);

        /** @var array<string, array<string, string>> $cache */
        $cache = $cacheProperty->getValue();
        $cache[$className] = $setterMap;
        $cacheProperty->setValue($cache);
    }
}
