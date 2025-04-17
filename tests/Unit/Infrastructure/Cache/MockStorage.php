<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cache;

use Spiral\RoadRunner\KeyValue\Serializer\SerializerAwareInterface;
use Spiral\RoadRunner\KeyValue\Serializer\SerializerInterface;
use Spiral\RoadRunner\KeyValue\StorageInterface;

/**
 * Тестовая реализация хранилища для юнит-тестов.
 */
final class MockStorage implements StorageInterface, SerializerAwareInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, \DateTimeInterface|null> */
    private array $ttl = [];

    private ?SerializerInterface $serializer = null;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->data[$key] = $value;

        if ($ttl !== null) {
            $dateTime = new \DateTimeImmutable();
            if ($ttl instanceof \DateInterval) {
                $this->ttl[$key] = $dateTime->add($ttl);
            } else {
                $this->ttl[$key] = $dateTime->modify("+{$ttl} seconds");
            }
        } else {
            $this->ttl[$key] = null;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key], $this->ttl[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->data = [];
        $this->ttl = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            // Гарантируем, что ключ будет строкой
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * @param non-empty-string $key
     */
    public function getTtl(string $key): ?\DateTimeInterface
    {
        return $this->ttl[$key] ?? null;
    }

    /**
     * @param iterable<string> $keys
     * @return array<string, \DateTimeInterface|null>
     */
    public function getMultipleTtl(iterable $keys = []): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $keyStr = (string) $key;
            if ($keyStr !== '') {
                $result[$keyStr] = $this->getTtl($keyStr);
            } else {
                $result[$keyStr] = null;
            }
        }

        return $result;
    }

    public function withSerializer(SerializerInterface $serializer): self
    {
        $new = clone $this;
        $new->serializer = $serializer;

        return $new;
    }

    public function getSerializer(): SerializerInterface
    {
        // В реальном классе здесь не может быть null, но для тестов нам нужно обойти это
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
        return 'mock';
    }
}
