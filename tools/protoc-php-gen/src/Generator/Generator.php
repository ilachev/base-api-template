<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use ProtoPhpGen\Model\EntityDescriptor;

/**
 * Interface for code generators.
 */
interface Generator
{
    /**
     * Generate code files from entity descriptor.
     *
     * @return GeneratedFile[]
     */
    public function generate(EntityDescriptor $descriptor): array;
}
