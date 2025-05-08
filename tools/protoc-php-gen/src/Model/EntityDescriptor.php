<?php

declare(strict_types=1);

namespace ProtoPhpGen\Model;

/**
 * Describes an entity to be generated.
 */
final class EntityDescriptor
{
    /**
     * @var PropertyDescriptor[]
     */
    private array $properties = [];

    private string $type = 'entity';

    /**
     * @param string $name Entity class name
     * @param string $namespace Entity namespace
     * @param string $tableName Database table name
     * @param string $primaryKey Primary key field name
     */
    public function __construct(
        private string $name,
        private string $namespace,
        private string $tableName,
        private string $primaryKey = 'id',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * @return PropertyDescriptor[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addProperty(PropertyDescriptor $property): self
    {
        $this->properties[] = $property;

        return $this;
    }

    /**
     * Get the fully qualified class name.
     */
    public function getFullyQualifiedName(): string
    {
        return $this->namespace . '\\' . $this->name;
    }

    /**
     * Get the generator type for this entity.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the generator type.
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
