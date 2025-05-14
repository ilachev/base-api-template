<?php

declare(strict_types=1);

namespace ProtoPhpGen\Model;

/**
 * Describes mapping between a domain class and a proto message.
 */
final class ClassMapping
{
    /**
     * @var FieldMapping[]
     */
    private array $fieldMappings = [];

    public function __construct(
        private string $domainClass,
        private string $protoClass,
        private ?string $transformerClass = null,
    ) {}

    public function getDomainClass(): string
    {
        return $this->domainClass;
    }

    public function getProtoClass(): string
    {
        return $this->protoClass;
    }

    public function getTransformerClass(): ?string
    {
        return $this->transformerClass;
    }

    /**
     * @return FieldMapping[]
     */
    public function getFieldMappings(): array
    {
        return $this->fieldMappings;
    }

    public function addFieldMapping(FieldMapping $fieldMapping): self
    {
        $this->fieldMappings[] = $fieldMapping;

        return $this;
    }
}
