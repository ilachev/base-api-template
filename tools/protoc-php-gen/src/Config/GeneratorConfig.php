<?php

declare(strict_types=1);

namespace ProtoPhpGen\Config;

/**
 * Configuration for hydrator generators.
 */
final class GeneratorConfig
{
    /**
     * @param string $namespace Base namespace for generated code
     * @param string $outputDir Output directory for generated files
     * @param string|null $hydratorInterface Full class name of hydrator interface
     * @param string|null $hydratorClass Full class name of hydrator class
     * @param string|null $domainNamespace Namespace for domain entities
     * @param string|null $protoNamespace Namespace for proto messages
     * @param bool $generateHydrators Whether to generate standard hydrators
     * @param bool $generateProtoHydrators Whether to generate proto hydrators
     * @param bool $standaloneMode Whether to generate code without external dependencies
     * @param array<string, string> $typeMapping Mapping between proto types and PHP types
     * @param string|null $outputPattern Pattern for output file paths
     */
    public function __construct(
        private string $namespace = 'App\Gen',
        private string $outputDir = 'gen',
        private ?string $hydratorInterface = 'App\Infrastructure\Hydrator\TypedHydrator',
        private ?string $hydratorClass = 'App\Infrastructure\Hydrator\Hydrator',
        private ?string $domainNamespace = null,
        private ?string $protoNamespace = null,
        private bool $generateHydrators = true,
        private bool $generateProtoHydrators = false,
        private bool $standaloneMode = false,
        private array $typeMapping = [],
        private ?string $outputPattern = null,
    ) {}

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    public function setOutputDir(string $outputDir): self
    {
        $this->outputDir = $outputDir;

        return $this;
    }

    public function getHydratorInterface(): ?string
    {
        return $this->hydratorInterface;
    }

    public function setHydratorInterface(?string $hydratorInterface): self
    {
        $this->hydratorInterface = $hydratorInterface;

        return $this;
    }

    public function getHydratorClass(): ?string
    {
        return $this->hydratorClass;
    }

    public function setHydratorClass(?string $hydratorClass): self
    {
        $this->hydratorClass = $hydratorClass;

        return $this;
    }

    public function getDomainNamespace(): ?string
    {
        return $this->domainNamespace;
    }

    public function setDomainNamespace(?string $domainNamespace): self
    {
        $this->domainNamespace = $domainNamespace;

        return $this;
    }

    public function getProtoNamespace(): ?string
    {
        return $this->protoNamespace;
    }

    public function setProtoNamespace(?string $protoNamespace): self
    {
        $this->protoNamespace = $protoNamespace;

        return $this;
    }

    public function shouldGenerateHydrators(): bool
    {
        return $this->generateHydrators;
    }

    public function setGenerateHydrators(bool $generateHydrators): self
    {
        $this->generateHydrators = $generateHydrators;

        return $this;
    }

    public function shouldGenerateProtoHydrators(): bool
    {
        return $this->generateProtoHydrators;
    }

    public function setGenerateProtoHydrators(bool $generateProtoHydrators): self
    {
        $this->generateProtoHydrators = $generateProtoHydrators;

        return $this;
    }

    public function isStandaloneMode(): bool
    {
        return $this->standaloneMode;
    }

    public function setStandaloneMode(bool $standaloneMode): self
    {
        $this->standaloneMode = $standaloneMode;

        return $this;
    }

    public function getTypeMapping(): array
    {
        return $this->typeMapping;
    }

    public function setTypeMapping(array $typeMapping): self
    {
        $this->typeMapping = $typeMapping;

        return $this;
    }

    public function getOutputPattern(): ?string
    {
        return $this->outputPattern;
    }

    public function setOutputPattern(?string $outputPattern): self
    {
        $this->outputPattern = $outputPattern;

        return $this;
    }

    /**
     * Get output path for a generated file.
     * Allows configuration of output paths using placeholders.
     *
     * @param string $className Class name without namespace
     * @param string $type Type of generated file (e.g. 'hydrator', 'proto_hydrator')
     * @param string $defaultPath Default path if pattern is not set
     */
    public function getOutputPath(string $className, string $type, string $defaultPath): string
    {
        if ($this->outputPattern === null) {
            return $defaultPath;
        }

        $replacements = [
            '{className}' => $className,
            '{type}' => $type,
            '{outputDir}' => $this->outputDir,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $this->outputPattern);
    }

    /**
     * Map a proto type to a PHP type using configured type mapping.
     *
     * @param string $protoType Proto type name
     * @param string $defaultType Default PHP type if mapping not found
     */
    public function mapType(string $protoType, string $defaultType): string
    {
        return $this->typeMapping[$protoType] ?? $defaultType;
    }
}
