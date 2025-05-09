<?php

declare(strict_types=1);

namespace ProtoPhpGen\Model;

/**
 * Describes a property of an entity.
 */
final readonly class PropertyDescriptor
{
    /**
     * @param string $name Property name
     * @param string $type Property type (PHP type)
     * @param bool $nullable Whether property can be null
     * @param string|null $columnName Database column name (if different from property name)
     * @param string|null $protoType Original proto type
     * @param bool $repeated Whether the field is repeated in protobuf
     * @param bool $isJson Whether the field should be stored as JSON
     * @param bool $ignore Whether the field should be ignored in database operations
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public ?string $columnName = null,
        public ?string $protoType = null,
        public bool $repeated = false,
        public bool $isJson = false,
        public bool $ignore = false,
    ) {}

    /**
     * Get database column name (or property name if not specified).
     */
    public function getColumnName(): string
    {
        return $this->columnName ?? $this->toSnakeCase($this->name);
    }

    /**
     * Convert camelCase to snake_case.
     */
    private function toSnakeCase(string $input): string
    {
        // preg_replace возвращает null только если возникла ошибка в регулярном выражении
        // здесь это невозможно, так что безопасно приводим результат к string
        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);

        return strtolower($result !== null ? $result : $input);
    }
}
