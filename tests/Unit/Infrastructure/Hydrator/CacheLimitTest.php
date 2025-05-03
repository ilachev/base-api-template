<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator;

use App\Infrastructure\Hydrator\LimitedReflectionCache;
use App\Infrastructure\Hydrator\ProtobufHydration;
use App\Infrastructure\Hydrator\SetterProtobufHydration;
use Google\Protobuf\Internal\Message;
use PHPUnit\Framework\TestCase;

final class CacheLimitTest extends TestCase
{
    /**
     * Verify that the cache has a size limiting property.
     */
    public function testReflectionCacheLimitDefinition(): void
    {
        $cache = new LimitedReflectionCache();
        $reflection = new \ReflectionClass(LimitedReflectionCache::class);
        $property = $reflection->getProperty('maxCacheSize');
        $property->setAccessible(true);
        $maxCacheSize = $property->getValue($cache);

        self::assertIsInt($maxCacheSize);
        self::assertGreaterThan(10, $maxCacheSize, 'Cache size should be reasonably large');
        self::assertLessThan(1000, $maxCacheSize, 'Cache size should be bounded');
    }

    /**
     * Verifies that SetterProtobufHydration exists as replacement for ProtobufAdapter.
     */
    public function testProtobufHydrationExists(): void
    {
        self::assertTrue(interface_exists(ProtobufHydration::class));
        self::assertTrue(class_exists(SetterProtobufHydration::class));

        $implementation = new SetterProtobufHydration();
        self::assertInstanceOf(ProtobufHydration::class, $implementation);
    }

    /**
     * Verifies that LimitedReflectionCache has cache limiting mechanism.
     */
    public function testCacheHasCacheLimitingMechanism(): void
    {
        // Verify the cache class has cache limiting code
        $filename = (new \ReflectionClass(LimitedReflectionCache::class))->getFileName();
        self::assertNotFalse($filename);
        $source = file_get_contents((string) $filename);
        self::assertNotFalse($source);

        // Using simple string checking to verify cache limiting code exists
        self::assertStringContainsString('reset($cache)', $source);
        self::assertStringContainsString('key($cache)', $source);
        self::assertStringContainsString('unset($cache[$firstKey])', $source);

        // Verify the specific limiting logic is present
        self::assertStringContainsString('if (\count($cache) >= $this->maxCacheSize)', $source);
    }

    /**
     * This test directly checks the cache limiting logic in LimitedReflectionCache.
     */
    public function testCacheLimitingLogic(): void
    {
        $reflection = new \ReflectionClass(LimitedReflectionCache::class);

        // Extract the manageCache method
        $manageCacheMethod = $reflection->getMethod('manageCache');
        $manageCacheStartLine = $manageCacheMethod->getStartLine();
        $manageCacheEndLine = $manageCacheMethod->getEndLine();

        $filename = (string) $reflection->getFileName();
        self::assertFileExists($filename);
        $sourceCode = file_get_contents($filename);
        self::assertNotFalse($sourceCode);

        $sourceLines = explode("\n", $sourceCode);
        $manageCacheSource = implode("\n", \array_slice($sourceLines, $manageCacheStartLine - 1, $manageCacheEndLine - $manageCacheStartLine + 1));

        // Verify the cache limiting code structure is present and correct
        self::assertStringContainsString('if (\count($cache) >= $this->maxCacheSize)', $manageCacheSource);
        self::assertStringContainsString('reset($cache)', $manageCacheSource);
        self::assertStringContainsString('$firstKey = key($cache)', $manageCacheSource);
        self::assertStringContainsString('unset($cache[$firstKey])', $manageCacheSource);
    }

    /**
     * Тест на проверку поведения кеша protobufCache через прямую манипуляцию.
     */
    public function testProtobufCacheLimiting(): void
    {
        // Создаем кеш с маленьким размером
        $cache = new LimitedReflectionCache(5);

        // Получаем доступ к приватным полям и методам
        $reflection = new \ReflectionClass(LimitedReflectionCache::class);
        $protobufCacheProperty = $reflection->getProperty('protobufCache');
        $protobufCacheProperty->setAccessible(true);

        $manageCacheMethod = $reflection->getMethod('manageCache');
        $manageCacheMethod->setAccessible(true);

        // Получаем исходное состояние кеша
        /** @var array<string, bool> $cacheData */
        $cacheData = $protobufCacheProperty->getValue($cache);

        // Начинаем с пустого кеша
        self::assertCount(0, $cacheData, 'Начальный кеш должен быть пустым');

        // Вручную добавляем элементы в кеш через метод manageCache
        for ($i = 0; $i < 10; ++$i) {
            $className = 'TestClass' . $i;
            $value = ($i % 2) === 0;

            /** @var array<string, bool> $cacheRef */
            $cacheRef = $protobufCacheProperty->getValue($cache);
            $manageCacheMethod->invokeArgs($cache, [&$cacheRef, $className, $value]);
            $protobufCacheProperty->setValue($cache, $cacheRef);
        }

        // Получаем итоговое состояние кеша
        /** @var array<string, bool> $finalCacheData */
        $finalCacheData = $protobufCacheProperty->getValue($cache);

        // Проверяем, что размер кеша не превышает установленный лимит
        self::assertLessThanOrEqual(
            5,
            \count($finalCacheData),
            'Размер кеша protobufCache должен быть ограничен максимальным значением',
        );
    }

    /**
     * Проверяет поведение механизма кеширования для рефлексии классов.
     */
    public function testReflectionClassCacheBehavior(): void
    {
        // Создаем кеш с очень маленьким размером
        $cache = new LimitedReflectionCache(3);

        // Получаем доступ к приватному полю reflectionCache
        $reflection = new \ReflectionClass(LimitedReflectionCache::class);
        $reflectionCacheProperty = $reflection->getProperty('reflectionCache');
        $reflectionCacheProperty->setAccessible(true);

        // Список существующих классов для тестирования
        $classesToTest = [
            self::class,
            TestCase::class,
            LimitedReflectionCache::class,
            \stdClass::class,
            \Exception::class,
        ];

        // Вызываем метод getReflectionClass для каждого из классов
        foreach ($classesToTest as $className) {
            $cache->getReflectionClass($className);
        }

        // Получаем актуальное состояние кеша
        /** @var array<string, \ReflectionClass<object>> $reflectionCacheData */
        $reflectionCacheData = $reflectionCacheProperty->getValue($cache);

        // Проверяем, что размер кеша ограничен
        self::assertLessThanOrEqual(
            3,
            \count($reflectionCacheData),
            'Размер кеша reflectionCache должен быть ограничен',
        );
    }

    public function testIsProtobufMessageWithExistingClasses(): void
    {
        $cache = new LimitedReflectionCache();

        $result = $cache->isProtobufMessage(TestCase::class);
        self::assertFalse($result, 'TestCase не должен определяться как Protobuf-сообщение');

        $result = $cache->isProtobufMessage(Message::class);
        self::assertFalse($result, 'Message::class не должен определяться как подкласс самого себя');
    }

    public function testIsProtobufMessageCaching(): void
    {
        $cache = new LimitedReflectionCache();

        $cache->isProtobufMessage(TestCase::class);

        $reflection = new \ReflectionClass(LimitedReflectionCache::class);
        $protobufCacheProperty = $reflection->getProperty('protobufCache');
        $protobufCacheProperty->setAccessible(true);

        /** @var array<string, bool> $cacheData */
        $cacheData = $protobufCacheProperty->getValue($cache);

        self::assertArrayHasKey(TestCase::class, $cacheData);
        self::assertFalse($cacheData[TestCase::class]);
        $cacheData[TestCase::class] = true;
        $protobufCacheProperty->setValue($cache, $cacheData);

        $result = $cache->isProtobufMessage(TestCase::class);
        self::assertTrue($result, 'Метод должен вернуть значение из кеша');
    }
}
