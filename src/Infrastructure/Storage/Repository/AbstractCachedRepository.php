<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Repository;

use App\Infrastructure\Cache\CacheService;

abstract class AbstractCachedRepository
{
    private const CACHE_TTL = 3600;

    private bool $cacheAvailable;

    public function __construct(
        protected readonly CacheService $cache,
    ) {
        // Проверяем доступность кеша при инициализации
        $this->cacheAvailable = $this->cache->isAvailable();
    }

    protected function setCacheValue(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->cacheAvailable) {
            return;
        }

        $this->cache->set($key, $value, $ttl ?? self::CACHE_TTL);
    }

    protected function getCacheValue(string $key, mixed $default = null): mixed
    {
        if (!$this->cacheAvailable) {
            return $default;
        }

        return $this->cache->get($key, $default);
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    protected function getOrSetCacheValue(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!$this->cacheAvailable) {
            return $callback();
        }

        return $this->cache->getOrSet($key, $callback, $ttl ?? self::CACHE_TTL);
    }

    protected function deleteCacheValue(string $key): void
    {
        if (!$this->cacheAvailable) {
            return;
        }

        $this->cache->delete($key);
    }

    protected function hasCacheValue(string $key): bool
    {
        if (!$this->cacheAvailable) {
            return false;
        }

        return $this->cache->has($key);
    }
}
