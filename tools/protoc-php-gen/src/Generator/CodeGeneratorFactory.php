<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Config\GeneratorConfig;

/**
 * Factory to create appropriate hydrator generators.
 */
final readonly class CodeGeneratorFactory
{
    public function __construct(
        private GeneratorConfig $config,
    ) {}

    /**
     * Create a generator for the specified hydrator type.
     *
     * @param string $type Hydrator type
     * @throws \InvalidArgumentException If type is not supported
     */
    public function createGenerator(string $type): Generator
    {
        return match ($type) {
            'hydrator' => new HydratorGenerator($this->config),
            'proto_hydrator' => new ProtoHydratorGenerator($this->config),
            'standalone_hydrator' => new StandaloneHydratorGenerator($this->config),
            default => throw new \InvalidArgumentException("Unsupported generator type: {$type}"),
        };
    }
}
