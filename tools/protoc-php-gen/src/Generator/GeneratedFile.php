<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

/**
 * Represents a generated file.
 */
final readonly class GeneratedFile
{
    /**
     * @param string $name File name (path)
     * @param string $content File content
     */
    public function __construct(
        private string $name,
        private string $content,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
