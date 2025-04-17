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
    ) {}

    /**
     * @param array{
     *     engine: string,
     *     address: string,
     *     default_prefix: string,
     *     default_ttl: int,
     * } $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            engine: $config['engine'],
            address: $config['address'],
            defaultPrefix: $config['default_prefix'],
            defaultTtl: $config['default_ttl'],
        );
    }
}
