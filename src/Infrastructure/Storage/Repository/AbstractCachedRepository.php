<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Repository;

use App\Infrastructure\Cache\CacheService;
use App\Infrastructure\Logger\Logger;

abstract readonly class AbstractCachedRepository
{
    private const int CACHE_TTL = 3600;

    private bool $cacheAvailable;

    public function __construct(
        protected CacheService $cache,
        protected Logger $logger,
        protected string $cacheKeyPrefix = '',
    ) {
        // Проверяем доступность кеша при инициализации
        $this->cacheAvailable = $this->cache->isAvailable();
    }

    protected function setCacheValue(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->cacheAvailable) {
            return;
        }

        $prefixedKey = $this->buildCacheKey($key);
        $this->cache->set($prefixedKey, $value, $ttl ?? self::CACHE_TTL);
        $this->logger->debug('Cache set', [
            'key' => $key,
            'repository' => static::class,
        ]);
    }

    protected function getCacheValue(string $key, mixed $default = null): mixed
    {
        if (!$this->cacheAvailable) {
            $this->logCacheMiss($key, 'cache unavailable');

            return $default;
        }

        $prefixedKey = $this->buildCacheKey($key);
        $value = $this->cache->get($prefixedKey, $default);

        if ($value === $default) {
            $this->logCacheMiss($key, 'not found');
        } else {
            $this->logCacheHit($key);
        }

        return $value;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    protected function getOrSetCacheValue(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!$this->cacheAvailable) {
            $result = $callback();
            $this->logCacheMiss($key, 'cache unavailable');

            return $result;
        }

        $prefixedKey = $this->buildCacheKey($key);

        // Проверяем наличие в кеше сначала
        $cachedValue = $this->cache->get($prefixedKey);

        if ($cachedValue !== null) {
            $this->logCacheHit($key);

            return $cachedValue;
        }

        // Если в кеше нет, вычисляем и сохраняем
        $result = $callback();
        $this->logCacheMiss($key, 'not found');

        $this->cache->set($prefixedKey, $result, $ttl ?? self::CACHE_TTL);

        return $result;
    }

    /**
     * Логирует успешное получение данных из кеша (cache hit).
     */
    protected function logCacheHit(string $key): void
    {
        $this->logger->debug('Cache hit', [
            'key' => $key,
            'repository' => static::class,
        ]);
    }

    /**
     * Логирует промах кеша (cache miss).
     */
    protected function logCacheMiss(string $key, string $reason): void
    {
        $this->logger->debug('Cache miss', [
            'key' => $key,
            'reason' => $reason,
            'repository' => static::class,
        ]);
    }

    /**
     * Формирует полный ключ кеша с префиксом.
     */
    protected function buildCacheKey(string $key): string
    {
        return $this->cacheKeyPrefix . $key;
    }

    protected function deleteCacheValue(string $key): void
    {
        if (!$this->cacheAvailable) {
            return;
        }

        $prefixedKey = $this->buildCacheKey($key);
        $this->cache->delete($prefixedKey);
        $this->logger->debug('Cache delete', [
            'key' => $key,
            'repository' => static::class,
        ]);
    }

    protected function hasCacheValue(string $key): bool
    {
        if (!$this->cacheAvailable) {
            $this->logCacheMiss($key, 'cache unavailable');

            return false;
        }

        $prefixedKey = $this->buildCacheKey($key);
        $exists = $this->cache->has($prefixedKey);

        if ($exists) {
            $this->logCacheHit($key);
        } else {
            $this->logCacheMiss($key, 'not found');
        }

        return $exists;
    }
}
