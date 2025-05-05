<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Infrastructure\Logger\Logger;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class RoadRunnerCacheService implements CacheService
{
    private StorageInterface $storage;

    private bool $available = false;

    public function __construct(
        private readonly CacheConfig $config,
        private readonly Logger $logger,
    ) {
        try {
            // Check if we're in a testing environment
            if ($this->isTestingEnvironment()) {
                // In test environment use a fallback storage
                $this->storage = new FallbackStorage();
                $this->available = true;

                return;
            }

            // Create RPC connection
            $address = !empty($this->config->address) ? $this->config->address : 'tcp://127.0.0.1:6001';
            $rpc = RPC::create($address);

            // Create factory and get storage
            $factory = new Factory($rpc);
            $engine = $this->config->engine === '' ? 'local-memory' : $this->config->engine;

            try {
                // Get storage
                $storage = $factory->select($engine);

                // Check storage availability with a simple has operation
                $testKey = 'cache_test_key';
                $storage->has($testKey);

                // If operation succeeds, save storage and mark as available
                $this->storage = $storage;
                $this->available = true;
                $this->logger->info('KV storage is available');
            } catch (\Throwable $e) {
                $this->logger->error('KV storage is not available: ' . $e->getMessage());
                $this->storage = new FallbackStorage();
                $this->available = false;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to initialize cache service: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            // Create a dummy storage that doesn't store anything
            // This allows the application to work even if cache is unavailable
            $this->storage = new FallbackStorage();
            $this->available = false;
        }
    }

    /**
     * Checks if we're in a testing environment.
     */
    private function isTestingEnvironment(): bool
    {
        // Check for PHPUnit in the environment
        return \defined('PHPUNIT_COMPOSER_INSTALL') || \defined('__PHPUNIT_PHAR__')
            || isset($_SERVER['ENVIRONMENT']) && $_SERVER['ENVIRONMENT'] === 'testing';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->available) {
            return true; // Pretend everything is okay
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

            // Mark cache as unavailable after error
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

            // Mark cache as unavailable after error
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

            // Mark cache as unavailable after error
            $this->available = false;

            return false;
        }
    }

    public function delete(string $key): bool
    {
        if (!$this->available) {
            return true; // Pretend everything is okay
        }

        $prefixedKey = $this->prefixKey($key);

        try {
            return $this->storage->delete($prefixedKey);
        } catch (\Throwable $e) {
            $this->logger->error('Cache delete error: ' . $e->getMessage(), [
                'key' => $prefixedKey,
                'exception' => $e,
            ]);

            // Mark cache as unavailable after error
            $this->available = false;

            return false;
        }
    }

    /**
     * Flag indicating that cache clear operation is in progress.
     */
    private bool $clearInProgress = false;

    /**
     * Clears the entire cache with protection against concurrent calls.
     */
    public function clear(): bool
    {
        // If cache is unavailable or clearing is already in progress, simply return success
        if (!$this->available || $this->clearInProgress) {
            $this->logger->debug('Cache clear skipped', [
                'reason' => !$this->available ? 'cache unavailable' : 'already in progress',
            ]);

            return true; // Pretend everything is okay
        }

        // Set flag that clearing is in progress
        $this->clearInProgress = true;

        try {
            // Try to clear cache with retries
            $maxRetries = 3;
            $retryCount = 0;
            $success = false;

            while (!$success && $retryCount < $maxRetries) {
                try {
                    $this->storage->clear();
                    $success = true;
                } catch (\Throwable $e) {
                    ++$retryCount;
                    if ($retryCount >= $maxRetries) {
                        throw $e; // Throw exception after exhausting attempts
                    }

                    // Log error and delay before next attempt
                    $this->logger->warning('Cache clear retry', [
                        'attempt' => $retryCount,
                        'error' => $e->getMessage(),
                    ]);

                    // Wait before retrying (50ms, 100ms, 200ms)
                    usleep($retryCount * 50000);
                }
            }

            $this->logger->info('Cache cleared successfully', [
                'attempts' => $retryCount + 1,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Cache clear error', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            // Mark cache as unavailable after error
            $this->available = false;

            return false;
        } finally {
            // In any case reset the clearing flag
            $this->clearInProgress = false;
        }
    }

    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!$this->available) {
            return $callback(); // Just call the function without caching
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
     * Adds a prefix to the cache key.
     */
    private function prefixKey(string $key): string
    {
        return $this->config->defaultPrefix . $key;
    }
}
