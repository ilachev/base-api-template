<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class CacheConfig
{
    public function __construct(
        public string $engine,
        public string $address,
        public string $defaultPrefix,
        public int $defaultTtl,
        public bool $compression = true,
        public int $compressionThreshold = 1024,
        public int $maxTtl = 604800,
    ) {}

    /**
     * @param array{
     *     engine: string,
     *     address: string,
     *     default_prefix: string,
     *     default_ttl: int,
     *     compression?: bool,
     *     compression_threshold?: int,
     *     max_ttl?: int,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            engine: $config['engine'],
            address: $config['address'],
            defaultPrefix: $config['default_prefix'],
            defaultTtl: $config['default_ttl'],
            compression: $config['compression'] ?? true,
            compressionThreshold: $config['compression_threshold'] ?? 1024,
            maxTtl: $config['max_ttl'] ?? 604800,
        );
    }
}
