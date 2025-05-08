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
     * @param string|null $entityInterface Full class name of entity interface
     * @param string|null $hydratorInterface Full class name of hydrator interface
     * @param string|null $repositoryBaseClass Full class name of repository base class
     * @param string|null $storageClass Full class name of storage class
     * @param string|null $hydratorClass Full class name of hydrator class
     * @param bool $generateRepositories Whether to generate repositories
     * @param bool $generateHydrators Whether to generate hydrators
     * @param bool $standaloneMode Whether to generate code without external dependencies
     */
    public function __construct(
        private string $namespace = 'App\Gen',
        private string $outputDir = 'gen',
        private ?string $entityInterface = 'App\Domain\Entity',
        private ?string $hydratorInterface = 'App\Infrastructure\Hydrator\TypedHydrator',
        private ?string $repositoryBaseClass = 'App\Infrastructure\Storage\Repository\AbstractRepository',
        private ?string $storageClass = 'App\Infrastructure\Storage\Storage',
        private ?string $hydratorClass = 'App\Infrastructure\Hydrator\Hydrator',
        private bool $generateRepositories = true,
        private bool $generateHydrators = true,
        private bool $standaloneMode = false,
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

    public function getEntityInterface(): ?string
    {
        return $this->entityInterface;
    }

    public function setEntityInterface(?string $entityInterface): self
    {
        $this->entityInterface = $entityInterface;

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

    public function getRepositoryBaseClass(): ?string
    {
        return $this->repositoryBaseClass;
    }

    public function setRepositoryBaseClass(?string $repositoryBaseClass): self
    {
        $this->repositoryBaseClass = $repositoryBaseClass;

        return $this;
    }

    public function getStorageClass(): ?string
    {
        return $this->storageClass;
    }

    public function setStorageClass(?string $storageClass): self
    {
        $this->storageClass = $storageClass;

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

    public function isStandaloneMode(): bool
    {
        return $this->standaloneMode;
    }

    public function setStandaloneMode(bool $standaloneMode): self
    {
        $this->standaloneMode = $standaloneMode;

        return $this;
    }
}
