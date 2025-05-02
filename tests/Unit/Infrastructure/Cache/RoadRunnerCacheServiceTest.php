<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cache;

use App\Infrastructure\Cache\CacheConfig;
use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Cache\RoadRunnerCacheService;
use App\Infrastructure\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Logger\TestLogger;

final class RoadRunnerCacheServiceTest extends TestCase
{
    private CacheConfig $config;

    private Logger $logger;

    private CacheService $cacheService;

    private MockStorage $mockStorage;

    protected function setUp(): void
    {
        // Создаем конфигурацию
        $this->config = new CacheConfig(
            engine: 'memory',
            address: 'tcp://localhost:6001',
            defaultPrefix: 'test:',
            defaultTtl: 60,
        );

        // Создаем логгер
        $this->logger = new TestLogger();

        // Создаем тестовое хранилище
        $this->mockStorage = new MockStorage();

        // Создаем сервис кеширования
        $this->cacheService = new RoadRunnerCacheService(
            $this->config,
            $this->logger,
        );

        // Устанавливаем наше тестовое хранилище через рефлексию
        $reflectionProperty = new \ReflectionProperty(RoadRunnerCacheService::class, 'storage');
        $reflectionProperty->setValue($this->cacheService, $this->mockStorage);
    }

    public function testSetAndGet(): void
    {
        // Установка значения
        $key = 'test-key';
        $value = ['foo' => 'bar'];

        self::assertTrue($this->cacheService->set($key, $value));

        // Получение значения
        $retrieved = $this->cacheService->get($key);
        self::assertSame($value, $retrieved);
    }

    public function testGetNonExistentKey(): void
    {
        $key = 'non-existent';
        $default = 'default-value';

        $value = $this->cacheService->get($key, $default);
        self::assertSame($default, $value);
    }

    public function testHas(): void
    {
        $key = 'existing-key';
        $value = 'test-value';

        // Проверка до установки
        self::assertFalse($this->cacheService->has($key));

        // Установка значения
        $this->cacheService->set($key, $value);

        // Проверка после установки
        self::assertTrue($this->cacheService->has($key));
    }

    public function testDelete(): void
    {
        $key = 'to-delete';
        $value = 'delete-me';

        // Установка значения
        $this->cacheService->set($key, $value);
        self::assertTrue($this->cacheService->has($key));

        // Удаление значения
        self::assertTrue($this->cacheService->delete($key));
        self::assertFalse($this->cacheService->has($key));
    }

    public function testClear(): void
    {
        // Установка нескольких значений
        $this->cacheService->set('key1', 'value1');
        $this->cacheService->set('key2', 'value2');

        self::assertTrue($this->cacheService->has('key1'));
        self::assertTrue($this->cacheService->has('key2'));

        // Очистка кеша
        self::assertTrue($this->cacheService->clear());

        self::assertFalse($this->cacheService->has('key1'));
        self::assertFalse($this->cacheService->has('key2'));
    }

    public function testGetOrSet(): void
    {
        $key = 'computed-value';
        $computeCount = 0;

        $callback = static function () use (&$computeCount) {
            ++$computeCount;

            return 'computed-result';
        };

        // Первый вызов - значения нет в кеше, должен вызваться callback
        $result1 = $this->cacheService->getOrSet($key, $callback);
        self::assertSame('computed-result', $result1);
        self::assertSame(1, $computeCount, 'Callback должен быть вызван ровно 1 раз');

        // Второй вызов - значение должно быть в кеше, callback не должен вызываться
        $result2 = $this->cacheService->getOrSet($key, $callback);
        self::assertSame('computed-result', $result2);
        // Проверяем, что колбэк не был вызван второй раз (счетчик не изменился)
        self::assertSame(1, $computeCount, 'Callback не должен вызываться повторно');
    }
}
