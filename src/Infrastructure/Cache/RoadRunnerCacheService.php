<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Psr\Log\LoggerInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class RoadRunnerCacheService implements CacheService
{
    private StorageInterface $storage;

    public function __construct(
        private readonly CacheConfig $config,
        private readonly LoggerInterface $logger,
    ) {
        try {
            // Создаем RPC соединение
            $address = !empty($this->config->address) ? $this->config->address : 'tcp://127.0.0.1:6001';
            $rpc = RPC::create($address);

            // Создаем фабрику и получаем хранилище
            $factory = new Factory($rpc);
            $engine = $this->config->engine === '' ? 'local-memory' : $this->config->engine;
            $this->storage = $factory->select($engine);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to initialize cache service: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            // Создаем хранилище-заглушку, которое ничего не хранит
            // Это позволяет приложению работать даже если кеш недоступен
            $this->storage = new FallbackStorage();
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $prefixedKey = $this->prefixKey($key);
        $ttl ??= $this->config->defaultTtl;

        try {
            $this->storage->set($prefixedKey, $value, $ttl);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Cache set error: ' . $e->getMessage(), [
                'key' => $prefixedKey,
                'exception' => $e,
            ]);

            return false;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $prefixedKey = $this->prefixKey($key);

        try {
            $value = $this->storage->get($prefixedKey);

            if ($value === null) {
                return $default;
            }

            return $value;
        } catch (\Throwable $e) {
            $this->logger->error('Cache get error: ' . $e->getMessage(), [
                'key' => $prefixedKey,
                'exception' => $e,
            ]);

            return $default;
        }
    }

    public function has(string $key): bool
    {
        $prefixedKey = $this->prefixKey($key);

        try {
            return $this->storage->has($prefixedKey);
        } catch (\Throwable $e) {
            $this->logger->error('Cache has error: ' . $e->getMessage(), [
                'key' => $prefixedKey,
                'exception' => $e,
            ]);

            return false;
        }
    }

    public function delete(string $key): bool
    {
        $prefixedKey = $this->prefixKey($key);

        try {
            return $this->storage->delete($prefixedKey);
        } catch (\Throwable $e) {
            $this->logger->error('Cache delete error: ' . $e->getMessage(), [
                'key' => $prefixedKey,
                'exception' => $e,
            ]);

            return false;
        }
    }

    public function clear(): bool
    {
        try {
            $this->storage->clear();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Cache clear error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return false;
        }
    }

    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Добавляет префикс к ключу кеша.
     */
    private function prefixKey(string $key): string
    {
        return $this->config->defaultPrefix . $key;
    }
}
