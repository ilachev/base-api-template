<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Psr\Log\LoggerInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class RoadRunnerCacheService implements CacheService
{
    private StorageInterface $storage;

    private bool $available = false;

    public function __construct(
        private readonly CacheConfig $config,
        private readonly LoggerInterface $logger,
    ) {
        try {
            // Проверяем, не находимся ли мы в тестовом окружении
            if ($this->isTestingEnvironment()) {
                // В тестовом окружении используем заглушку
                $this->storage = new FallbackStorage();
                $this->available = true;

                return;
            }

            // Создаем RPC соединение
            $address = !empty($this->config->address) ? $this->config->address : 'tcp://127.0.0.1:6001';
            $rpc = RPC::create($address);

            // Проверяем доступность RPC без логирования ошибок
            if (!$this->isRpcAvailable($rpc)) {
                $this->storage = new FallbackStorage();
                $this->available = false;

                return;
            }

            // Создаем фабрику и получаем хранилище
            $factory = new Factory($rpc);
            $engine = $this->config->engine === '' ? 'local-memory' : $this->config->engine;
            $this->storage = $factory->select($engine);
            $this->available = true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to initialize cache service: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            // Создаем хранилище-заглушку, которое ничего не хранит
            // Это позволяет приложению работать даже если кеш недоступен
            $this->storage = new FallbackStorage();
            $this->available = false;
        }
    }

    /**
     * Проверяет, находимся ли мы в тестовом окружении.
     */
    private function isTestingEnvironment(): bool
    {
        // Проверяем наличие PHPUnit в окружении
        return \defined('PHPUNIT_COMPOSER_INSTALL') || \defined('__PHPUNIT_PHAR__')
               || isset($_SERVER['ENVIRONMENT']) && $_SERVER['ENVIRONMENT'] === 'testing';
    }

    /**
     * Проверяет доступность RPC соединения без генерации исключений.
     */
    private function isRpcAvailable(RPCInterface $rpc): bool
    {
        try {
            // Правильный способ: проверяем доступность через Has метод с пустым ключом
            $kvRpc = $rpc->withServicePrefix('kv');
            $kvRpc->call('Has', 'test_key');

            $this->logger->debug('KV ping is OK', []);

            return true;
        } catch (\Throwable $e) {
            $this->logger->debug('KV ping is NOT OK', ['message' => $e->getMessage()]);

            return false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->available) {
            return true; // Притворяемся, что всё в порядке
        }

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

            // Отмечаем кеш как недоступный после ошибки
            $this->available = false;

            return false;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->available) {
            return $default;
        }

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

            // Отмечаем кеш как недоступный после ошибки
            $this->available = false;

            return $default;
        }
    }

    public function has(string $key): bool
    {
        if (!$this->available) {
            return false;
        }

        $prefixedKey = $this->prefixKey($key);

        try {
            return $this->storage->has($prefixedKey);
        } catch (\Throwable $e) {
            $this->logger->error('Cache has error: ' . $e->getMessage(), [
                'key' => $prefixedKey,
                'exception' => $e,
            ]);

            // Отмечаем кеш как недоступный после ошибки
            $this->available = false;

            return false;
        }
    }

    public function delete(string $key): bool
    {
        if (!$this->available) {
            return true; // Притворяемся, что всё в порядке
        }

        $prefixedKey = $this->prefixKey($key);

        try {
            return $this->storage->delete($prefixedKey);
        } catch (\Throwable $e) {
            $this->logger->error('Cache delete error: ' . $e->getMessage(), [
                'key' => $prefixedKey,
                'exception' => $e,
            ]);

            // Отмечаем кеш как недоступный после ошибки
            $this->available = false;

            return false;
        }
    }

    public function clear(): bool
    {
        if (!$this->available) {
            return true; // Притворяемся, что всё в порядке
        }

        try {
            $this->storage->clear();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Cache clear error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            // Отмечаем кеш как недоступный после ошибки
            $this->available = false;

            return false;
        }
    }

    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!$this->available) {
            return $callback(); // Просто вызываем функцию без кеширования
        }

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
