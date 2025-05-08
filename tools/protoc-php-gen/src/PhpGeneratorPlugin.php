<?php

declare(strict_types=1);

namespace ProtoPhpGen;

use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\CodeGeneratorFactory;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;
use ProtoPhpGen\Protoc\PluginRequest;
use ProtoPhpGen\Protoc\PluginResponse;
use ProtoPhpGen\Protoc\ProtocPlugin;

/**
 * Plugin for generating PHP code from proto files.
 */
final readonly class PhpGeneratorPlugin extends ProtocPlugin
{
    /**
     * Map of protobuf types to PHP types.
     *
     * @var array<int, string>
     */
    private const array PROTO_TYPE_MAP = [
        1 => 'double',   // TYPE_DOUBLE
        2 => 'float',    // TYPE_FLOAT
        3 => 'int',      // TYPE_INT64
        4 => 'int',      // TYPE_UINT64
        5 => 'int',      // TYPE_INT32
        6 => 'float',    // TYPE_FIXED64
        7 => 'int',      // TYPE_FIXED32
        8 => 'bool',     // TYPE_BOOL
        9 => 'string',   // TYPE_STRING
        10 => 'object',  // TYPE_GROUP
        11 => 'object',  // TYPE_MESSAGE
        12 => 'string',  // TYPE_BYTES
        13 => 'int',     // TYPE_UINT32
        14 => 'int',     // TYPE_ENUM
        15 => 'int',     // TYPE_SFIXED32
        16 => 'int',     // TYPE_SFIXED64
        17 => 'int',     // TYPE_SINT32
        18 => 'int',     // TYPE_SINT64
    ];

    /**
     * Processes requests from protoc and generates files.
     */
    public function process(PluginRequest $request): PluginResponse
    {
        $response = new PluginResponse();

        try {
            // Debug logging
            $this->logDebug('Starting code generation');
            $this->logDebug('Files to generate: ' . implode(', ', $request->getFilesToGenerate()));
            $this->logDebug('Parameters: ' . json_encode($request->getParameters()));

            // Create generator configuration
            $config = $this->createConfig($request);

            // Get generator factory
            $generatorFactory = new CodeGeneratorFactory($config);

            // Get files to generate
            $filesToGenerate = array_flip($request->getFilesToGenerate());

            // Get proto files
            $protoFiles = $request->getProtoFiles();

            // Process each proto file for generation
            foreach ($protoFiles as $fileName => $protoFile) {
                // Skip files not in the generation list
                if (!isset($filesToGenerate[$fileName])) {
                    continue;
                }

                $this->logDebug("Processing file: {$fileName}");

                // Get file options
                $fileOptions = $protoFile['options'] ?? [];

                // Get namespace from file options or from package
                $namespace = $fileOptions['php_namespace'] ?? null;
                if ($namespace === null && isset($protoFile['package'])) {
                    $namespace = $this->packageToNamespace($protoFile['package']);
                }

                // If namespace is not specified, use namespace from configuration
                if ($namespace === null) {
                    $namespace = $config->getNamespace();
                }

                // Get all message types from the file
                $messageTypes = $protoFile['message_type'] ?? [];

                // Create entity descriptors from message types
                foreach ($messageTypes as $messageType) {
                    $descriptor = $this->createEntityDescriptor($messageType, $namespace);

                    if ($descriptor === null) {
                        continue;
                    }

                    // Debug logging
                    $this->logDebug("Created entity descriptor: {$descriptor->getName()}");
                    $this->logDebug('Properties: ' . implode(', ', array_map(
                        static fn(PropertyDescriptor $p) => "{$p->name}:{$p->type}",
                        $descriptor->getProperties(),
                    )));

                    // Generate entity
                    $descriptor->setType('entity');
                    $generator = $generatorFactory->createGenerator($descriptor->getType());
                    $files = $generator->generate($descriptor);

                    foreach ($files as $file) {
                        $response->addFile($file->getName(), $file->getContent());
                        $this->logDebug("Generated file: {$file->getName()}");
                    }

                    // Generate hydrator if enabled
                    if ($config->shouldGenerateHydrators()) {
                        $descriptor->setType('hydrator');
                        $generator = $generatorFactory->createGenerator($descriptor->getType());
                        $files = $generator->generate($descriptor);

                        foreach ($files as $file) {
                            $response->addFile($file->getName(), $file->getContent());
                            $this->logDebug("Generated file: {$file->getName()}");
                        }
                    }

                    // Generate repository if enabled
                    if ($config->shouldGenerateRepositories()) {
                        $descriptor->setType('repository');
                        $generator = $generatorFactory->createGenerator($descriptor->getType());
                        $files = $generator->generate($descriptor);

                        foreach ($files as $file) {
                            $response->addFile($file->getName(), $file->getContent());
                            $this->logDebug("Generated file: {$file->getName()}");
                        }
                    }
                }
            }

            $this->logDebug('Code generation completed successfully');
        } catch (\Throwable $e) {
            $error = "Code generation error: {$e->getMessage()}";
            $response->setError($error);
            $this->logDebug($error);
            $this->logDebug("Stack trace: {$e->getTraceAsString()}");
        }

        return $response;
    }

    /**
     * Creates a generator configuration from request parameters.
     */
    private function createConfig(PluginRequest $request): GeneratorConfig
    {
        $config = new GeneratorConfig();

        // Process command line parameters
        if ($request->hasParameter('namespace')) {
            $config->setNamespace($request->getParameter('namespace'));
        }

        if ($request->hasParameter('output_dir')) {
            $config->setOutputDir($request->getParameter('output_dir'));
        }

        if ($request->hasParameter('entity_interface')) {
            $config->setEntityInterface($request->getParameter('entity_interface'));
        }

        if ($request->hasParameter('generate_repositories')) {
            $value = $request->getParameter('generate_repositories');
            $config->setGenerateRepositories($value === 'true' || $value === '1');
        }

        if ($request->hasParameter('generate_hydrators')) {
            $value = $request->getParameter('generate_hydrators');
            $config->setGenerateHydrators($value === 'true' || $value === '1');
        }

        return $config;
    }

    /**
     * Creates an entity descriptor from a message type.
     *
     * @param array<string, mixed> $messageType Message type
     * @param string $namespace PHP namespace
     */
    private function createEntityDescriptor(array $messageType, string $namespace): ?EntityDescriptor
    {
        // Get message name
        $messageName = $messageType['name'] ?? null;
        if ($messageName === null) {
            return null;
        }

        // Get message fields
        $fields = $messageType['field'] ?? [];
        if (empty($fields)) {
            return null;
        }

        // Create entity descriptor
        $entityDescriptor = new EntityDescriptor(
            name: $messageName,
            namespace: $namespace,
            tableName: $this->camelToSnake($messageName) . 's',
            primaryKey: 'id',
        );

        // Add properties
        foreach ($fields as $field) {
            $propertyDescriptor = $this->createPropertyDescriptor($field);
            if ($propertyDescriptor !== null) {
                $entityDescriptor->addProperty($propertyDescriptor);
            }
        }

        return $entityDescriptor;
    }

    /**
     * Creates a property descriptor from a message field.
     *
     * @param array<string, mixed> $field Message field
     */
    private function createPropertyDescriptor(array $field): ?PropertyDescriptor
    {
        // Get field name
        $fieldName = $field['name'] ?? null;
        if ($fieldName === null) {
            return null;
        }

        // Get field type
        $fieldType = $field['type'] ?? null;
        if ($fieldType === null) {
            return null;
        }

        // Get field label (required, optional, repeated)
        $fieldLabel = $field['label'] ?? null;

        // Determine PHP type for the field
        $phpType = self::PROTO_TYPE_MAP[$fieldType] ?? 'mixed';

        // Determine if the field is optional
        $isOptional = $fieldLabel === 1; // LABEL_OPTIONAL

        // Determine if the field is repeated
        $isRepeated = $fieldLabel === 3; // LABEL_REPEATED

        // For repeated fields, type is always array
        if ($isRepeated) {
            $phpType = 'array';
        }

        // Create property descriptor
        return new PropertyDescriptor(
            name: $this->snakeToCamel($fieldName),
            type: $phpType,
            nullable: $isOptional,
            columnName: $fieldName,
            protoType: (string) $fieldType,
            repeated: $isRepeated,
        );
    }

    /**
     * Converts package name to namespace.
     */
    private function packageToNamespace(string $package): string
    {
        $parts = explode('.', $package);
        $parts = array_map('ucfirst', $parts);

        return 'App\\' . implode('\\', $parts);
    }

    /**
     * Converts snake_case to camelCase.
     */
    private function snakeToCamel(string $input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }

    /**
     * Converts CamelCase to snake_case.
     */
    private function camelToSnake(string $input): string
    {
        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);

        return $result !== null ? strtolower($result) : strtolower($input);
    }

    /**
     * Outputs a debug message.
     */
    private function logDebug(string $message): void
    {
        if (getenv('PROTOC_PHP_GEN_DEBUG') === 'true') {
            fwrite(STDERR, "[DEBUG] {$message}\n");
        }
    }
}
