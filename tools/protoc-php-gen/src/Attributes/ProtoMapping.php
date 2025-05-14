<?php

declare(strict_types=1);

namespace ProtoPhpGen\Attributes;

/**
 * Attribute for mapping a domain class to a proto message.
 * Applied at the class level to define relationship with proto class.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ProtoMapping
{
    /**
     * @param string $class The fully qualified name of the proto class
     * @param string|null $transformerClass Optional custom transformer class for complex mapping
     */
    public function __construct(
        public readonly string $class,
        public readonly ?string $transformerClass = null,
    ) {}
}
