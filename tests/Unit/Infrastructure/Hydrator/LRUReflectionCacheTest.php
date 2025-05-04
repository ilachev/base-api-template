<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator;

use App\Infrastructure\Hydrator\HydratorException;
use App\Infrastructure\Hydrator\LRUReflectionCache;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Hydrator\Fixtures\TestProtobufMessage;

final class LRUReflectionCacheTest extends TestCase
{
    private LRUReflectionCache $cache;

    protected function setUp(): void
    {
        $this->cache = new LRUReflectionCache(3); // Small cache size for testing
    }

    public function testGetReflectionClass(): void
    {
        $reflection = $this->cache->getReflectionClass(\stdClass::class);

        self::assertInstanceOf(\ReflectionClass::class, $reflection);
        self::assertEquals(\stdClass::class, $reflection->getName());
    }

    public function testEvictionWhenCacheFull(): void
    {
        // We're using a cache with size 3 (from setUp)
        $this->cache->getReflectionClass(\stdClass::class);
        $this->cache->getReflectionClass(\ArrayObject::class);
        $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // This should cause \stdClass to be evicted as it was accessed first
        $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // Refresh cache for \DateTime to make it recently used
        $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // This should evict \ArrayObject not \DateTime
        $this->cache->getReflectionClass(\Exception::class);

        self::assertEquals(3, $this->cache->getSize());

        // Access the classes that should still be in cache
        $reflection1 = $this->cache->getReflectionClass(\DateTimeImmutable::class);
        $reflection2 = $this->cache->getReflectionClass(\DateTimeImmutable::class);
        $reflection3 = $this->cache->getReflectionClass(\Exception::class);

        self::assertEquals(\DateTimeImmutable::class, $reflection1->getName());
        self::assertEquals(\DateTimeImmutable::class, $reflection2->getName());
        self::assertEquals(\Exception::class, $reflection3->getName());
    }

    public function testGetConstructorParams(): void
    {
        $params = $this->cache->getConstructorParams(\DateTimeImmutable::class);

        self::assertNotEmpty($params);
        self::assertInstanceOf(\ReflectionParameter::class, $params[0]);
    }

    public function testGetConstructorParamsThrowsExceptionForClassWithoutConstructor(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Class stdClass must have a constructor');

        $this->cache->getConstructorParams(\stdClass::class);
    }

    public function testGetPublicProperties(): void
    {
        // Create a test class with public properties
        $testObj = new class {
            public string $foo = 'bar';

            // Private property used in this test to verify it's not included in getPublicProperties
            private string $baz = 'qux';

            public function getBaz(): string
            {
                return $this->baz;
            }
        };

        $properties = $this->cache->getPublicProperties($testObj::class);

        self::assertCount(1, $properties);
        self::assertEquals('foo', $properties[0]->getName());
    }

    public function testIsProtobufMessage(): void
    {
        // Test with standard class (not a Protobuf message)
        self::assertFalse($this->cache->isProtobufMessage(\stdClass::class));

        // Test with actual Protobuf class
        self::assertTrue($this->cache->isProtobufMessage(TestProtobufMessage::class));

        // Check that repeated calls return same result (caching works)
        $result1 = $this->cache->isProtobufMessage(\stdClass::class);
        $result2 = $this->cache->isProtobufMessage(\stdClass::class);
        self::assertSame($result1, $result2);
    }

    /**
     * Test behavior with non-existent class.
     */
    public function testIsProtobufMessageWithNonExistentClass(): void
    {
        // @phpstan-ignore-next-line
        self::assertFalse($this->cache->isProtobufMessage('App\NonExistentClass'));
    }

    public function testCacheClearing(): void
    {
        $this->cache->getReflectionClass(\stdClass::class);
        $this->cache->getReflectionClass(\ArrayObject::class);

        self::assertEquals(2, $this->cache->getSize());

        $this->cache->clear();

        self::assertEquals(0, $this->cache->getSize());
    }

    public function testCachingConsistency(): void
    {
        // First access should initialize the cache
        $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // Second access should return the same object from cache
        $reflection1 = $this->cache->getReflectionClass(\DateTimeImmutable::class);
        $reflection2 = $this->cache->getReflectionClass(\DateTimeImmutable::class);

        // Assert same instance
        self::assertSame($reflection1, $reflection2);
    }
}
