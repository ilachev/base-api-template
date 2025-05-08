<?php

declare(strict_types=1);

namespace ProtoPhpGen\Parser;

use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;

/**
 * Parser for .proto files.
 * Extracts information about messages and fields from an array of data
 * representing FileDescriptorProto.
 */
final class ProtoParser
{
    // Constants for field types in protobuf
    private const TYPE_DOUBLE = 1;
    private const TYPE_FLOAT = 2;
    private const TYPE_INT64 = 3;
    private const TYPE_UINT64 = 4;
    private const TYPE_INT32 = 5;
    private const TYPE_FIXED64 = 6;
    private const TYPE_FIXED32 = 7;
    private const TYPE_BOOL = 8;
    private const TYPE_STRING = 9;
    private const TYPE_GROUP = 10;
    private const TYPE_MESSAGE = 11;
    private const TYPE_BYTES = 12;
    private const TYPE_UINT32 = 13;
    private const TYPE_ENUM = 14;
    private const TYPE_SFIXED32 = 15;
    private const TYPE_SFIXED64 = 16;
    private const TYPE_SINT32 = 17;
    private const TYPE_SINT64 = 18;

    // Constants for field labels
    private const LABEL_OPTIONAL = 1;
    private const LABEL_REPEATED = 3;

    /**
     * Parses .proto file data and returns entity descriptors.
     *
     * @param array<string, mixed> $file .proto file data
     * @return EntityDescriptor[] Entity descriptors
     */
    public function parse(array $file): array
    {
        $descriptors = [];

        $protoPackage = $file['package'] ?? '';
        $phpNamespace = null;

        // Getting namespace from file options
        if (isset($file['options'])) {
            $options = $file['options'];
            if (isset($options['php_namespace'])) {
                $phpNamespace = $options['php_namespace'];
            }
        }

        // If namespace is not specified, generate it from package
        if ($phpNamespace === null) {
            $phpNamespace = $this->packageToNamespace($protoPackage);
        }

        // Process all messages in the file
        $messageTypes = $file['message_type'] ?? [];
        foreach ($messageTypes as $messageType) {
            // Check if the message is an entity
            if ($this->isEntity($messageType)) {
                $entityDescriptor = $this->createEntityDescriptor($messageType, $phpNamespace);
                if ($entityDescriptor !== null) {
                    $descriptors[] = $entityDescriptor;

                    // Also generate a hydrator for this entity
                    $hydratorDescriptor = clone $entityDescriptor;
                    $hydratorDescriptor->setType('hydrator');
                    $descriptors[] = $hydratorDescriptor;

                    // And a repository
                    $repositoryDescriptor = clone $entityDescriptor;
                    $repositoryDescriptor->setType('repository');
                    $descriptors[] = $repositoryDescriptor;
                }
            }
        }

        return $descriptors;
    }

    /**
     * Checks if a message is an entity.
     *
     * @param array<string, mixed> $message Message data
     * @return bool True if the message is an entity
     */
    private function isEntity(array $message): bool
    {
        $messageName = $message['name'] ?? '';

        // Skip messages with certain suffixes that are not considered entities
        if (str_ends_with($messageName, 'DTO')
            || str_ends_with($messageName, 'Request')
            || str_ends_with($messageName, 'Response')) {
            return false;
        }

        // Check message options for is_entity
        if (isset($message['options'], $message['options']['is_entity'])) {
            return (bool) $message['options']['is_entity'];
        }

        // By default, consider message as an entity
        return true;
    }

    /**
     * Creates an entity descriptor from message data.
     *
     * @param array<string, mixed> $message Message data
     * @param string $namespace Namespace for the entity
     * @return EntityDescriptor|null Entity descriptor or null if creation failed
     */
    private function createEntityDescriptor(array $message, string $namespace): ?EntityDescriptor
    {
        $messageName = $message['name'] ?? null;

        if ($messageName === null) {
            return null;
        }

        // Get table name from options or generate from message name
        $tableName = null;
        if (isset($message['options'], $message['options']['table_name'])) {
            $tableName = $message['options']['table_name'];
        }

        if ($tableName === null) {
            $tableName = $this->camelToSnake($messageName) . 's';
        }

        // Get primary key from options or use 'id' by default
        $primaryKey = 'id';
        if (isset($message['options'], $message['options']['primary_key'])) {
            $primaryKey = $message['options']['primary_key'];
        }

        // Create entity descriptor
        $entityDescriptor = new EntityDescriptor(
            name: $messageName,
            namespace: $namespace,
            tableName: $tableName,
            primaryKey: $primaryKey,
        );

        // Add properties
        $fields = $message['field'] ?? [];
        foreach ($fields as $field) {
            $propertyDescriptor = $this->createPropertyDescriptor($field);
            if ($propertyDescriptor !== null) {
                $entityDescriptor->addProperty($propertyDescriptor);
            }
        }

        return $entityDescriptor;
    }

    /**
     * Creates a property descriptor from field data.
     *
     * @param array<string, mixed> $field Field data
     * @return PropertyDescriptor|null Property descriptor or null if creation failed
     */
    private function createPropertyDescriptor(array $field): ?PropertyDescriptor
    {
        $fieldName = $field['name'] ?? null;
        if ($fieldName === null) {
            return null;
        }

        $fieldType = $field['type'] ?? null;
        if ($fieldType === null) {
            return null;
        }

        // Get field label (required, optional, repeated)
        $fieldLabel = $field['label'] ?? null;

        // Determine PHP type
        $phpType = $this->mapFieldTypeToPhp($fieldType);

        // Determine if the field is optional
        $isOptional = $fieldLabel === self::LABEL_OPTIONAL;

        // Determine if the field is repeated
        $isRepeated = $fieldLabel === self::LABEL_REPEATED;

        // For repeated fields, type is always array
        if ($isRepeated) {
            $phpType = 'array';
        }

        // Get column name from options or use field name
        $columnName = null;
        if (isset($field['options'], $field['options']['column_name'])) {
            $columnName = $field['options']['column_name'];
        }

        if ($columnName === null) {
            $columnName = $fieldName;
        }

        // Create property descriptor
        return new PropertyDescriptor(
            name: $this->snakeToCamel($fieldName),
            type: $phpType,
            nullable: $isOptional,
            columnName: $columnName,
            protoType: (string) $fieldType,
            repeated: $isRepeated,
        );
    }

    /**
     * Converts protobuf field type to PHP type.
     *
     * @param int $fieldType Protobuf field type
     * @return string PHP type
     */
    private function mapFieldTypeToPhp(int $fieldType): string
    {
        return match ($fieldType) {
            self::TYPE_DOUBLE, self::TYPE_FLOAT => 'float',

            self::TYPE_INT64, self::TYPE_UINT64, self::TYPE_INT32,
            self::TYPE_UINT32, self::TYPE_FIXED64, self::TYPE_FIXED32,
            self::TYPE_SFIXED32, self::TYPE_SFIXED64,
            self::TYPE_SINT32, self::TYPE_SINT64 => 'int',

            self::TYPE_BOOL => 'bool',

            self::TYPE_STRING, self::TYPE_BYTES => 'string',

            self::TYPE_ENUM => 'string',

            self::TYPE_MESSAGE, self::TYPE_GROUP => 'object',

            default => 'mixed',
        };
    }

    /**
     * Converts protobuf package name to PHP namespace.
     *
     * @param string $package Package name
     * @return string PHP namespace
     */
    private function packageToNamespace(string $package): string
    {
        if (empty($package)) {
            return 'App';
        }

        $parts = explode('.', $package);
        $parts = array_map(
            static fn(string $part): string => ucfirst($part),
            $parts,
        );

        return 'App\\' . implode('\\', $parts);
    }

    /**
     * Converts snake_case to camelCase.
     *
     * @param string $input String in snake_case format
     * @return string String in camelCase format
     */
    private function snakeToCamel(string $input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }

    /**
     * Converts CamelCase to snake_case.
     *
     * @param string $input String in CamelCase format
     * @return string String in snake_case format
     */
    private function camelToSnake(string $input): string
    {
        if (empty($input)) {
            return '';
        }

        $pattern = '/(?<!^)[A-Z]/';
        $replacement = '_$0';
        $result = preg_replace($pattern, $replacement, $input);

        if ($result === null) {
            return strtolower($input);
        }

        return strtolower($result);
    }
}
