<?php

declare(strict_types=1);

namespace ProtoPhpGen\Model;

/**
 * Describes mapping between a domain entity property and a proto field.
 */
final class FieldMapping
{
    /**
     * @param string $domainProperty Name of property in domain entity
     * @param string $protoField Name of field in proto message
     * @param string $type Type of mapping
     * @param string|null $transformer Name of transformer function
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private string $domainProperty,
        private string $protoField,
        private string $type = 'default',
        private ?string $transformer = null,
        private array $options = [],
    ) {}

    public function getDomainProperty(): string
    {
        return $this->domainProperty;
    }

    public function getProtoField(): string
    {
        return $this->protoField;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTransformer(): ?string
    {
        return $this->transformer;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
