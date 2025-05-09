<?php

declare(strict_types=1);

namespace ProtoPhpGen\Config;

/**
 * Configuration for standalone mode of the generator.
 */
final class StandaloneConfig
{
    /**
     * @param string $domainDir Directory with domain classes
     * @param string $protoDir Directory with proto classes
     * @param string $outputDir Directory for generated hydrators
     * @param string $domainNamespace Namespace for domain classes
     * @param string $protoNamespace Namespace for proto classes
     * @param array<string, callable> $transformers Custom transformers
     */
    public function __construct(
        private string $domainDir,
        private string $protoDir,
        private string $outputDir,
        private string $domainNamespace,
        private string $protoNamespace,
        private array $transformers = [],
    ) {
    }

    public function getDomainDir(): string
    {
        return $this->domainDir;
    }

    public function getProtoDir(): string
    {
        return $this->protoDir;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir;
    }

    public function getDomainNamespace(): string
    {
        return $this->domainNamespace;
    }

    public function getProtoNamespace(): string
    {
        return $this->protoNamespace;
    }
    
    /**
     * @return array<string, callable>
     */
    public function getTransformers(): array
    {
        return $this->transformers;
    }
    
    /**
     * @param string $name
     * @param callable $transformer
     * @return $this
     */
    public function addTransformer(string $name, callable $transformer): self
    {
        $this->transformers[$name] = $transformer;
        return $this;
    }
}
