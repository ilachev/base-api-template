<?php

declare(strict_types=1);

namespace ProtoPhpGen\Attributes;

/**
 * Attribute for mapping a domain entity property to a proto message field.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ProtoField
{
    public const TYPE_DEFAULT = 'default';
    public const TYPE_JSON = 'json';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_ENUM = 'enum';
    
    /**
     * @param string $name Field name in proto message
     * @param string|null $transformer Optional transformer function name 
     * @param string $type Field type (default, json, datetime, enum)
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $transformer = null,
        public readonly string $type = self::TYPE_DEFAULT,
        public readonly array $options = [],
    ) {
    }
}
