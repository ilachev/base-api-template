<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Config\GeneratorConfig;

/**
 * Factory to create appropriate code generators.
 */
final readonly class CodeGeneratorFactory
{
    public function __construct(
        private GeneratorConfig $config,
    ) {}

    /**
     * Create a generator for the specified entity type.
     *
     * @param string $type Entity type
     * @throws \InvalidArgumentException If type is not supported
     */
    public function createGenerator(string $type): Generator
    {
        return match ($type) {
            'entity' => new EntityGenerator($this->config),
            'hydrator' => new HydratorGenerator($this->config),
            'repository' => new RepositoryGenerator($this->config),
            default => throw new \InvalidArgumentException("Unsupported generator type: {$type}"),
        };
    }
}
