<?php

declare(strict_types=1);

namespace ProtoPhpGen\Protoc;

use ProtoPhpGen\Protoc\Binary\CodeGeneratorResponseWriter;

/**
 * Represents the response from the plugin to the protocol compiler.
 * Simplified version of Google\Protobuf\Compiler\CodeGeneratorResponse.
 */
final class PluginResponse
{
    /**
     * @var array<PluginResponseFile> Generated files
     */
    private array $files = [];

    /**
     * @var string|null Error message if generation failed
     */
    private ?string $error = null;

    /**
     * Adds a generated file to the response.
     *
     * @param string $name File name (path)
     * @param string $content File content
     */
    public function addFile(string $name, string $content): void
    {
        $this->files[] = new PluginResponseFile($name, $content);
    }

    /**
     * Adds a file object to the response.
     */
    public function addFileObject(PluginResponseFile $file): void
    {
        $this->files[] = $file;
    }

    /**
     * Sets an error message.
     *
     * @param string $error Error message
     */
    public function setError(string $error): void
    {
        $this->error = $error;
    }

    /**
     * Checks if an error occurred.
     *
     * @return bool True if an error occurred
     */
    public function hasError(): bool
    {
        return $this->error !== null && $this->error !== '';
    }

    /**
     * Returns the error message.
     *
     * @return string|null Error message or null if there are no errors
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @return array<PluginResponseFile>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Converts the response to a format for sending to the protocol compiler.
     *
     * @return string Serialized response
     */
    public function serialize(): string
    {
        // In debug/development mode, return JSON instead of binary data
        if (getenv('PROTOC_PHP_GEN_DEBUG') === 'true') {
            $jsonResult = json_encode([
                'error' => $this->error,
                'files' => array_map(static fn(PluginResponseFile $file) => [
                    'name' => $file->getName(),
                    'content' => $file->getContent(),
                ], $this->files),
            ], JSON_PRETTY_PRINT);

            if ($jsonResult === false) {
                throw new \RuntimeException('Failed to encode response to JSON: ' . json_last_error_msg());
            }

            return $jsonResult;
        }

        // Otherwise, serialize to protobuf binary format
        $writer = new CodeGeneratorResponseWriter();
        $result = $writer->serialize($this);

        if ($result === '') {
            throw new \RuntimeException('Failed to serialize response');
        }

        return $result;
    }

    /**
     * Outputs the response to stdout.
     */
    public function write(): void
    {
        echo $this->serialize();
    }
}
