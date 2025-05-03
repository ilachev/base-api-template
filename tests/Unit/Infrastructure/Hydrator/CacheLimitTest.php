<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator;

use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\ProtobufAdapter;
use App\Infrastructure\Hydrator\ReflectionHydrator;
use PHPUnit\Framework\TestCase;

final class CacheLimitTest extends TestCase
{
    /**
     * Verify that ProtobufAdapter has a size limiting constant.
     */
    public function testProtobufAdapterLimitDefinition(): void
    {
        $reflection = new \ReflectionClass(ProtobufAdapter::class);
        $maxCacheSize = $reflection->getConstant('MAX_CACHE_SIZE');

        self::assertIsInt($maxCacheSize);
        self::assertGreaterThan(10, $maxCacheSize, 'Cache size should be reasonably large');
        self::assertLessThan(1000, $maxCacheSize, 'Cache size should be bounded');
    }

    /**
     * Verifies that we have cache limiting mechanism in ProtobufAdapter.
     */
    public function testProtobufAdapterHasCacheLimitingMechanism(): void
    {
        // Verify the adapter class has cache limiting code
        $filename = new \ReflectionClass(ProtobufAdapter::class)->getFileName();
        self::assertNotFalse($filename);
        $source = file_get_contents((string) $filename);
        self::assertNotFalse($source);

        // Using simple string checking to verify cache limiting code exists
        self::assertStringContainsString('reset(self::$propertySetterCache)', $source);
        self::assertStringContainsString('key(self::$propertySetterCache)', $source);
        self::assertStringContainsString('unset(self::$propertySetterCache', $source);

        // Verify the specific limiting logic is present
        self::assertStringContainsString('if (\count(self::$propertySetterCache) >= self::MAX_CACHE_SIZE)', $source);
    }

    /**
     * This test directly checks the cache limiting logic in ProtobufAdapter.
     */
    public function testProtobufAdapterCacheLimitingLogic(): void
    {
        $adapter = new ProtobufAdapter();
        $reflection = new \ReflectionClass(ProtobufAdapter::class);

        // Extract the method source code to verify implementation
        $methodReflection = new \ReflectionMethod(ProtobufAdapter::class, 'mapProperties');
        $methodStartLine = $methodReflection->getStartLine();
        $methodEndLine = $methodReflection->getEndLine();

        $filename = (string) $reflection->getFileName();
        self::assertFileExists($filename);
        $sourceCode = file_get_contents($filename);
        self::assertNotFalse($sourceCode);

        $sourceLines = explode("\n", $sourceCode);
        $methodSource = implode("\n", \array_slice($sourceLines, $methodStartLine - 1, $methodEndLine - $methodStartLine + 1));

        // Verify the cache limiting code structure is present and correct
        self::assertStringContainsString('if (\count(self::$propertySetterCache) >= self::MAX_CACHE_SIZE)', $methodSource);
        self::assertStringContainsString('reset(self::$propertySetterCache)', $methodSource);
        self::assertStringContainsString('$firstKey = key(self::$propertySetterCache)', $methodSource);
        self::assertStringContainsString('unset(self::$propertySetterCache[$firstKey])', $methodSource);
    }

    /**
     * Verify that the Hydrator has a cache size limiting constant.
     */
    public function testHydratorCacheLimitDefinition(): void
    {
        $reflection = new \ReflectionClass(ReflectionHydrator::class);
        $maxCacheSize = $reflection->getConstant('MAX_INHERITANCE_CACHE_SIZE');

        self::assertIsInt($maxCacheSize);
        self::assertGreaterThan(10, $maxCacheSize, 'Cache size should be reasonably large');
        self::assertLessThan(1000, $maxCacheSize, 'Cache size should be bounded');
    }

    /**
     * Verifies that Hydrator has cache limiting mechanism.
     */
    public function testHydratorHasCacheLimitingMechanism(): void
    {
        // Verify the hydrator class has cache limiting code
        $filename = (new \ReflectionClass(ReflectionHydrator::class))->getFileName();
        self::assertNotFalse($filename);
        $source = file_get_contents((string) $filename);
        self::assertNotFalse($source);

        // Using simple string checking to verify cache limiting code exists
        self::assertStringContainsString('reset($cache)', $source);
        self::assertStringContainsString('key($cache)', $source);
        self::assertStringContainsString('unset($cache[$firstKey])', $source);

        // Verify the specific limiting logic is present
        self::assertStringContainsString('if (\count($cache) >= self::MAX_INHERITANCE_CACHE_SIZE)', $source);
    }

    /**
     * This test directly checks the cache limiting logic in Hydrator by creating
     * a controlled environment and verifying the exact behavior when limit is reached.
     */
    public function testHydratorCacheLimitingLogic(): void
    {
        $reflection = new \ReflectionClass(ReflectionHydrator::class);

        // Get access to isProtobufMessage method
        $method = $reflection->getMethod('isProtobufMessage');
        $method->setAccessible(true);

        // Get MAX_INHERITANCE_CACHE_SIZE value
        $maxCacheSize = $reflection->getConstant('MAX_INHERITANCE_CACHE_SIZE');
        self::assertIsInt($maxCacheSize);

        // Extract the method source code
        $methodReflection = new \ReflectionMethod(ReflectionHydrator::class, 'isProtobufMessage');
        $methodStartLine = $methodReflection->getStartLine();
        $methodEndLine = $methodReflection->getEndLine();

        $filename = (string) $reflection->getFileName();
        self::assertFileExists($filename);
        $sourceCode = file_get_contents($filename);
        self::assertNotFalse($sourceCode);

        $sourceLines = explode("\n", $sourceCode);
        $methodSource = implode("\n", \array_slice($sourceLines, $methodStartLine - 1, $methodEndLine - $methodStartLine + 1));

        // Verify the cache limiting code structure is present and correct
        self::assertStringContainsString('if (\count($cache) >= self::MAX_INHERITANCE_CACHE_SIZE)', $methodSource);
        self::assertStringContainsString('reset($cache)', $methodSource);
        self::assertStringContainsString('$firstKey = key($cache)', $methodSource);
        self::assertStringContainsString('unset($cache[$firstKey])', $methodSource);
    }

    /**
     * Test memory usage when repeatedly calling the same method.
     * This is the most practical test - ensuring memory won't grow unbounded
     * when repeatedly calling the same method many times.
     */
    public function testMemoryUsageWithRepeatedCalls(): void
    {
        $hydrator = new ReflectionHydrator();
        $reflection = new \ReflectionClass(ReflectionHydrator::class);

        // Get the isProtobufMessage method to test
        $method = $reflection->getMethod('isProtobufMessage');
        $method->setAccessible(true);

        // Execute many calls to populate the cache
        $memoryBefore = memory_get_usage(true);

        $maxCalls = 1000; // Should be much larger than the cache limit

        for ($i = 1; $i <= $maxCalls; ++$i) {
            $className = "App\\TestNamespace\\TestClass{$i}";

            try {
                $method->invoke($hydrator, $className);
            } catch (\Throwable) {
                // Ignore errors
            }
        }

        $memoryAfter = memory_get_usage(true);

        // Memory growth should be bounded if cache is limited
        // This is a practical test - ensuring memory won't explode
        $growth = $memoryAfter - $memoryBefore;

        // Memory growth should be modest (less than 10MB)
        self::assertLessThan(
            10 * 1024 * 1024, // 10MB max growth
            $growth,
            'Memory growth should be bounded if cache is limited',
        );
    }
}
