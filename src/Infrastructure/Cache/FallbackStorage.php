<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Spiral\RoadRunner\KeyValue\Serializer\SerializerInterface;
use Spiral\RoadRunner\KeyValue\StorageInterface;

/**
 * Реализация хранилища-заглушки для случаев, когда основное хранилище недоступно.
 * Все операции выполняются без ошибок, но никакие данные не сохраняются.
 */
final class FallbackStorage implements StorageInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $default;
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function getTtl(string $key): ?\DateTimeInterface
    {
        return null;
    }

    /**
     * @param iterable<string> $keys
     * @return array<string, \DateTimeInterface|null>
     */
    public function getMultipleTtl(iterable $keys = []): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = null;
        }

        return $result;
    }

    private ?SerializerInterface $serializer = null;

    public function withSerializer(SerializerInterface $serializer): self
    {
        $new = clone $this;
        $new->serializer = $serializer;

        return $new;
    }

    public function getSerializer(): SerializerInterface
    {
        // Возвращаем дефолтный сериализатор, если не был установлен
        return $this->serializer ?? new class implements SerializerInterface {
            public function serialize(mixed $value): string
            {
                if (\is_string($value)) {
                    return $value;
                }

                $encoded = json_encode($value);

                return $encoded !== false ? $encoded : '';
            }

            public function unserialize(string $value): mixed
            {
                return $value;
            }
        };
    }

    public function getName(): string
    {
        return 'fallback';
    }
}
