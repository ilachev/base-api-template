<?php

declare(strict_types=1);

namespace ProtoPhpGen\Protoc;

use ProtoPhpGen\Protoc\Binary\CodeGeneratorRequestParser;

/**
 * Represents a request from protoc to the plugin.
 * Simplified version of Google\Protobuf\Compiler\CodeGeneratorRequest.
 */
final class PluginRequest
{
    /**
     * @var array<string> List of .proto files specified on the command line
     */
    private array $filesToGenerate = [];

    /**
     * @var string Parameters passed to the plugin
     */
    private string $parameter = '';

    /**
     * @var array<string, mixed> File descriptor sets for all files
     */
    private array $protoFiles = [];

    /**
     * Creates a request object from stdin binary data.
     *
     * @param string $rawInput Binary data from protoc
     * @return self Request object
     */
    public static function fromStdin(string $rawInput): self
    {
        // In debug/development mode, JSON input can be accepted instead of binary data
        if (!empty($rawInput) && $rawInput[0] === '{') {
            $request = new self();
            $data = json_decode($rawInput, true);

            if (json_last_error() === JSON_ERROR_NONE && \is_array($data)) {
                if (isset($data['filesToGenerate']) && \is_array($data['filesToGenerate'])) {
                    foreach ($data['filesToGenerate'] as $file) {
                        $request->addFileToGenerate($file);
                    }
                }

                if (isset($data['parameter'])) {
                    $request->setParameter($data['parameter']);
                }

                if (isset($data['protoFiles']) && \is_array($data['protoFiles'])) {
                    foreach ($data['protoFiles'] as $name => $file) {
                        $request->addProtoFile($name, $file);
                    }
                }
            }

            return $request;
        }

        // If input data is not empty, parse the protobuf binary format
        if (!empty($rawInput)) {
            try {
                $parser = new CodeGeneratorRequestParser();

                return $parser->parse($rawInput);
            } catch (\Throwable $e) {
                // On parsing error, output an error message and return an empty request
                fwrite(STDERR, 'Request parsing error: ' . $e->getMessage() . "\n");

                if (getenv('PROTOC_PHP_GEN_DEBUG') === 'true') {
                    fwrite(STDERR, $e->getTraceAsString() . "\n");
                }
            }
        }

        // If the input data is empty or a parsing error occurred,
        // return an empty request
        return new self();
    }

    /**
     * Sets the command line parameter.
     */
    public function setParameter(string $parameter): void
    {
        $this->parameter = $parameter;
    }

    /**
     * Adds a .proto file for generation.
     */
    public function addFileToGenerate(string $file): void
    {
        $this->filesToGenerate[] = $file;
    }

    /**
     * Checks if parameters contain the specified key.
     */
    public function hasParameter(string $key): bool
    {
        $options = [];
        parse_str($this->parameter, $options);

        return isset($options[$key]);
    }

    /**
     * Returns the parameter value by key.
     *
     * @param string $key Parameter key
     * @param string $defaultValue Default value
     * @return string Parameter value
     */
    public function getParameter(string $key, string $defaultValue = ''): string
    {
        $options = [];
        parse_str($this->parameter, $options);

        if (!isset($options[$key])) {
            return $defaultValue;
        }

        // Make sure the value is a string
        $value = $options[$key];
        if (\is_array($value)) {
            return implode(',', $value);
        }

        return (string) $value;
    }

    /**
     * Returns all parameters as an array.
     *
     * @return array<string, string> Parameters
     */
    public function getParameters(): array
    {
        $options = [];
        parse_str($this->parameter, $options);

        // Convert all values to strings
        $result = [];
        foreach ($options as $key => $value) {
            // Make sure the key is a string
            $stringKey = (string) $key;

            if (\is_array($value)) {
                $result[$stringKey] = implode(',', $value);
            } else {
                $result[$stringKey] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    public function getFilesToGenerate(): array
    {
        return $this->filesToGenerate;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProtoFiles(): array
    {
        return $this->protoFiles;
    }

    /**
     * Adds a proto file.
     *
     * @param string $name File name
     * @param array<string, mixed> $data File content
     */
    public function addProtoFile(string $name, array $data): void
    {
        $this->protoFiles[$name] = $data;
    }

    /**
     * Returns a proto file by name.
     *
     * @param string $name File name
     * @return array<string, mixed>|null File content or null if the file is not found
     */
    public function getProtoFile(string $name): ?array
    {
        return $this->protoFiles[$name] ?? null;
    }
}
