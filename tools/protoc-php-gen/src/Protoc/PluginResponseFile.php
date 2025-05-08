<?php

declare(strict_types=1);

namespace ProtoPhpGen\Protoc;

/**
 * Represents a file to be included in the plugin response.
 * Simplified version of Google\Protobuf\Compiler\CodeGeneratorResponse\File.
 */
final readonly class PluginResponseFile
{
    /**
     * @param string $name File name (path)
     * @param string $content File content
     */
    public function __construct(
        private string $name,
        private string $content,
    ) {}

    /**
     * Returns the file name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the file content.
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
