<?php

declare(strict_types=1);

namespace ProtoPhpGen\Config;

/**
 * Configuration for code generators.
 */
final class GeneratorConfig
{
    /**
     * @param string $namespace Base namespace for generated code
     * @param string $outputDir Output directory for generated files
     * @param string $entityInterface Full class name of entity interface
     * @param bool $generateRepositories Whether to generate repositories
     * @param bool $generateHydrators Whether to generate hydrators
     */
    public function __construct(
        private string $namespace = 'App\Gen',
        private string $outputDir = 'gen',
        private string $entityInterface = 'App\Domain\Entity',
        private bool $generateRepositories = true,
        private bool $generateHydrators = true,
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

    public function getEntityInterface(): string
    {
        return $this->entityInterface;
    }

    public function setEntityInterface(string $entityInterface): self
    {
        $this->entityInterface = $entityInterface;

        return $this;
    }

    public function shouldGenerateRepositories(): bool
    {
        return $this->generateRepositories;
    }

    public function setGenerateRepositories(bool $generateRepositories): self
    {
        $this->generateRepositories = $generateRepositories;

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
}
