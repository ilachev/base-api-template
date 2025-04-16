<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator;

use App\Api\V1\HomeData;
use App\Infrastructure\Hydrator\HydratorException;
use App\Infrastructure\Hydrator\ProtobufAdapter;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Hydrator\Fixtures\TestProtobufInvalidSetter;
use Tests\Unit\Infrastructure\Hydrator\Fixtures\TestProtobufLike;

final class ProtobufAdapterTest extends TestCase
{
    private ProtobufAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new ProtobufAdapter();

        // Reset the static cache before each test
        $this->resetPropertySetterCache();
    }

    /**
     * Uses reflection to reset the static property cache.
     */
    private function resetPropertySetterCache(): void
    {
        $reflectionClass = new \ReflectionClass(ProtobufAdapter::class);
        $cacheProperty = $reflectionClass->getProperty('propertySetterCache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue([]);
    }

    /**
     * Retrieves the current cache contents for assertions.
     *
     * @return array<string, array<string, string>>
     */
    private function getPropertySetterCache(): array
    {
        $reflectionClass = new \ReflectionClass(ProtobufAdapter::class);
        $cacheProperty = $reflectionClass->getProperty('propertySetterCache');
        $cacheProperty->setAccessible(true);

        /** @var array<string, array<string, string>> $cache */
        $cache = $cacheProperty->getValue();

        return $cache;
    }

    public function testHydrateProtobufObject(): void
    {
        $data = [
            'message' => 'Test message',
        ];

        /** @var HomeData $result */
        $result = $this->adapter->hydrate(HomeData::class, $data);

        self::assertInstanceOf(HomeData::class, $result);
        self::assertEquals('Test message', $result->getMessage());

        // Verify the cache was populated
        $cache = $this->getPropertySetterCache();
        self::assertArrayHasKey(HomeData::class, $cache);
        self::assertArrayHasKey('message', $cache[HomeData::class]);
        self::assertEquals('setMessage', $cache[HomeData::class]['message']);
    }

    public function testHydrateMultiplePropertiesOnCustomObject(): void
    {
        $data = [
            'message' => 'Hello World',
            'number' => 42,
        ];

        /** @var TestProtobufLike $result */
        $result = $this->adapter->hydrate(TestProtobufLike::class, $data);

        self::assertInstanceOf(TestProtobufLike::class, $result);
        self::assertEquals('Hello World', $result->getMessage());
        self::assertEquals(42, $result->getNumber());

        // Check cache for our test class
        $cache = $this->getPropertySetterCache();
        self::assertArrayHasKey(TestProtobufLike::class, $cache);
        self::assertCount(2, $cache[TestProtobufLike::class]);
    }

    /**
     * PHPStan doesn't like "NonExistentClass" not being a real class,
     * but that's exactly what we're testing here.
     *
     * @psalm-suppress InvalidArgument
     */
    public function testHydrateWithNonExistentClass(): void
    {
        self::expectException(HydratorException::class);
        self::expectExceptionMessage('Class NonExistentClass does not exist');

        /** @phpstan-ignore-next-line */
        $this->adapter->hydrate('NonExistentClass', ['foo' => 'bar']);
    }

    public function testHydrateWithNonExistentSetter(): void
    {
        $data = [
            'nonExistentProperty' => 'This property does not exist',
            'message' => 'This is valid',
        ];

        /** @var HomeData $result */
        $result = $this->adapter->hydrate(HomeData::class, $data);

        // Should skip non-existent property but set the valid one
        self::assertEquals('This is valid', $result->getMessage());
    }

    public function testHydrateThrowsExceptionOnSetterFailure(): void
    {
        self::expectException(HydratorException::class);
        self::expectExceptionMessage('Failed to set property value on Tests\Unit\Infrastructure\Hydrator\Fixtures\TestProtobufInvalidSetter using setValue');

        $this->adapter->hydrate(TestProtobufInvalidSetter::class, ['value' => 'test']);
    }

    // Cache limiting mechanism is tested in CacheLimitingAdapterTest
    // to avoid issues with shared state in static properties

    public function testBuildSetterMapCreatesCorrectMap(): void
    {
        // First, hydrate an object to populate the cache
        $this->adapter->hydrate(TestProtobufLike::class, ['message' => 'Test']);

        // Get the cache and inspect the setter map
        $cache = $this->getPropertySetterCache();
        self::assertArrayHasKey(TestProtobufLike::class, $cache);

        $setterMap = $cache[TestProtobufLike::class];
        self::assertArrayHasKey('message', $setterMap);
        self::assertEquals('setMessage', $setterMap['message']);
        self::assertArrayHasKey('number', $setterMap);
        self::assertEquals('setNumber', $setterMap['number']);
    }

    public function testSecondHydrationUsesCache(): void
    {
        // First, hydrate an object to populate the cache
        $this->adapter->hydrate(TestProtobufLike::class, ['message' => 'First call']);

        // Get the cache state after first call
        $cacheAfterFirstCall = $this->getPropertySetterCache();

        // Since we can't mock the final class, let's count cache accesses by monitoring changes
        $cacheSize = \count($cacheAfterFirstCall);

        // Second hydration of the same class
        $this->adapter->hydrate(TestProtobufLike::class, ['message' => 'Second call']);

        // Verify cache structure hasn't changed (no new entries added)
        $cacheAfterSecondCall = $this->getPropertySetterCache();
        self::assertCount($cacheSize, $cacheAfterSecondCall, 'Cache size should remain the same');
        self::assertEquals(
            array_keys($cacheAfterFirstCall),
            array_keys($cacheAfterSecondCall),
            'Cache keys should remain the same',
        );
    }
}
