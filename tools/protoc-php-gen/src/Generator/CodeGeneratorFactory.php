<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Config\GeneratorConfig;

/**
 * Factory to create appropriate mapper generators.
 */
final readonly class CodeGeneratorFactory
{
    public function __construct(
        private GeneratorConfig $config,
    ) {}

    /**
     * Create a generator for the specified mapper type.
     *
     * @param string $type Mapper type
     * @throws \InvalidArgumentException If type is not supported
     */
    public function createGenerator(string $type): Generator
    {
        return match ($type) {
            'mapper' => new MapperGenerator($this->config),
            'proto_mapper' => new ProtoMapperGenerator($this->config),
            'proto_domain_mapper' => new ProtoDomainMapperGenerator(),
            default => throw new \InvalidArgumentException("Unsupported generator type: {$type}"),
        };
    }
}
