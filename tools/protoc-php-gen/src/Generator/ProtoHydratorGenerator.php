<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Model\EntityDescriptor;

/**
 * Generator for proto hydrators that map between domain entities and proto messages.
 */
final readonly class ProtoHydratorGenerator implements Generator
{
    private PsrPrinter $printer;

    public function __construct(
        private GeneratorConfig $config,
    ) {
        $this->printer = new PsrPrinter();
    }

    /**
     * Generate proto hydrator class for given entity descriptor.
     *
     * @param EntityDescriptor $descriptor Entity descriptor
     * @return array<GeneratedFile> Generated file(s)
     */
    public function generate(EntityDescriptor $descriptor): array
    {
        // Skip generation if proto hydrators are disabled
        if (!$this->config->shouldGenerateProtoHydrators()) {
            return [];
        }

        // Determine domain class namespace and name
        $domainNamespace = $this->config->getDomainNamespace()
            ?? $this->config->getNamespace() . '\Domain';
        $domainClass = $domainNamespace . '\\' . $descriptor->getName();

        // Determine proto class namespace and name
        $protoNamespace = $this->config->getProtoNamespace();
        if ($protoNamespace === null) {
            // Try to find matching proto class based on convention
            $protoNamespace = str_replace('\Domain', '\Api\V1', $domainNamespace);
        }
        $protoClass = $protoNamespace . '\\' . $descriptor->getName() . 'Proto';

        // Create hydrator class
        $file = new PhpFile();
        $file->setStrictTypes();

        $hydratorNamespace = $this->config->getNamespace() . '\Infrastructure\Hydrator\Proto';
        $namespace = $file->addNamespace($hydratorNamespace);

        $namespace->addUse($domainClass);
        $namespace->addUse($protoClass);
        $namespace->addUse(\RuntimeException::class);

        $className = $descriptor->getName() . 'ProtoHydrator';
        $hydratorClass = $namespace->addClass($className);
        $hydratorClass->setFinal(true);

        // Add hydrate method (proto to domain)
        $this->addHydrateMethod($hydratorClass, $descriptor, $domainClass, $protoClass);

        // Add extract method (domain to proto)
        $this->addExtractMethod($hydratorClass, $descriptor, $domainClass, $protoClass);

        // Generate file
        $content = $this->printer->printFile($file);

        // Use configured output pattern or fallback to default
        $defaultPath = $this->config->getOutputDir() . '/Infrastructure/Hydrator/Proto/'
                     . $className . '.php';

        $filePath = $this->config->getOutputPath(
            $className,
            'proto_hydrator',
            $defaultPath,
        );

        return [new GeneratedFile($filePath, $content)];
    }

    /**
     * Add hydrate method to convert proto message to domain entity
     * with improved error handling and type conversion.
     *
     * @param ClassType $class The class to add the method to
     * @param EntityDescriptor $descriptor Entity descriptor with property information
     * @param string $domainClass Fully qualified domain class name
     * @param string $protoClass Fully qualified proto class name
     * @throws \RuntimeException If method generation fails
     */
    private function addHydrateMethod(
        ClassType $class,
        EntityDescriptor $descriptor,
        string $domainClass,
        string $protoClass,
    ): void {
        $domainShort = $this->getShortClassName($domainClass);
        $protoShort = $this->getShortClassName($protoClass);

        $method = $class->addMethod('hydrate');
        $method->setReturnType($domainShort);
        $method->addComment("Convert {$protoShort} message to {$domainShort} entity.");
        $method->addComment('');
        $method->addComment("@param {$protoShort} \$proto Proto message");
        $method->addComment("@return {$domainShort} Domain entity");
        $method->addComment('@throws \\RuntimeException If conversion fails due to missing or invalid data');

        $method->addParameter('proto')
            ->setType($protoShort);

        $body = "try {\n";
        $body .= "    \$entity = new {$domainShort}();\n\n";

        // Map properties with error handling
        foreach ($descriptor->getProperties() as $property) {
            $propName = $property->name;
            $setter = 'set' . ucfirst($propName);
            $getterName = $this->camelToSnake($propName);
            $getter = 'get' . ucfirst($getterName);

            // Add error handling for property conversion
            $body .= "    // Convert {$propName} property\n";
            $body .= "    try {\n";

            // Determine PHP type based on configuration or default
            $phpType = $property->type;
            if (!empty($property->protoType)) {
                $phpType = $this->config->mapType($property->protoType, $phpType);
            }

            // Improved type handling with null checks and conversions based on type
            if ($phpType === '\DateTime' || $phpType === 'DateTime' || $phpType === '\DateTimeInterface') {
                $body .= "        \$dateValue = \$proto->{$getter}();\n";
                $body .= "        if (\$dateValue !== null && \$dateValue !== '') {\n";
                $body .= "            \$entity->{$setter}(new \\DateTime(\$dateValue));\n";
                $body .= "        }\n";
            } elseif ($phpType === 'array' || $property->repeated) {
                $body .= "        \$arrayValue = \$proto->{$getter}();\n";
                $body .= "        if (\$arrayValue !== null) {\n";
                $body .= "            \$entity->{$setter}(\$arrayValue);\n";
                $body .= "        } else {\n";
                $body .= "            \$entity->{$setter}([]);\n";
                $body .= "        }\n";
            } elseif (str_contains($phpType, '\\') && $phpType !== '\DateTime' && $phpType !== '\DateTimeInterface') {
                // Assume this is a complex type that needs instantiation
                $body .= "        \$value = \$proto->{$getter}();\n";
                $body .= "        if (\$value !== null) {\n";
                $body .= "            // Nested object conversion\n";
                $body .= "            \$nestedObject = new {$phpType}();\n";
                $body .= "            // TODO: Add nested object conversion based on properties\n";
                $body .= "            \$entity->{$setter}(\$nestedObject);\n";
                $body .= "        }\n";
            } elseif ($property->nullable) {
                $body .= "        \$value = \$proto->{$getter}();\n";

                // Add type conversion if needed
                if ($phpType === 'int' || $phpType === 'integer') {
                    $body .= "        if (\$value !== null) {\n";
                    $body .= "            \$entity->{$setter}((int)\$value);\n";
                    $body .= "        } else {\n";
                    $body .= "            \$entity->{$setter}(null);\n";
                    $body .= "        }\n";
                } elseif ($phpType === 'float' || $phpType === 'double') {
                    $body .= "        if (\$value !== null) {\n";
                    $body .= "            \$entity->{$setter}((float)\$value);\n";
                    $body .= "        } else {\n";
                    $body .= "            \$entity->{$setter}(null);\n";
                    $body .= "        }\n";
                } elseif ($phpType === 'bool' || $phpType === 'boolean') {
                    $body .= "        if (\$value !== null) {\n";
                    $body .= "            \$entity->{$setter}((bool)\$value);\n";
                    $body .= "        } else {\n";
                    $body .= "            \$entity->{$setter}(null);\n";
                    $body .= "        }\n";
                } elseif ($phpType === 'string') {
                    $body .= "        if (\$value !== null) {\n";
                    $body .= "            \$entity->{$setter}((string)\$value);\n";
                    $body .= "        } else {\n";
                    $body .= "            \$entity->{$setter}(null);\n";
                    $body .= "        }\n";
                } else {
                    $body .= "        \$entity->{$setter}(\$value);\n";
                }
            } else {
                // For non-nullable fields, ensure they have default values with appropriate type casting
                $body .= "        \$value = \$proto->{$getter}();\n";

                if ($phpType === 'int' || $phpType === 'integer') {
                    $body .= "        \$entity->{$setter}((int)\$value);\n";
                } elseif ($phpType === 'float' || $phpType === 'double') {
                    $body .= "        \$entity->{$setter}((float)\$value);\n";
                } elseif ($phpType === 'bool' || $phpType === 'boolean') {
                    $body .= "        \$entity->{$setter}((bool)\$value);\n";
                } elseif ($phpType === 'string') {
                    $body .= "        \$entity->{$setter}((string)\$value);\n";
                } else {
                    $body .= "        \$entity->{$setter}(\$value);\n";
                }
            }

            $body .= "    } catch (\\Throwable \$e) {\n";
            $body .= "        throw new \\RuntimeException('Failed to convert {$propName} property: ' . \$e->getMessage(), 0, \$e);\n";
            $body .= "    }\n\n";
        }

        $body .= "    return \$entity;\n";
        $body .= "} catch (\\Throwable \$e) {\n";
        $body .= "    throw new \\RuntimeException('Failed to hydrate {$domainShort} entity: ' . \$e->getMessage(), 0, \$e);\n";
        $body .= '}';

        $method->setBody($body);
    }

    /**
     * Add extract method to convert domain entity to proto message
     * with improved error handling and type conversion.
     *
     * @param ClassType $class The class to add the method to
     * @param EntityDescriptor $descriptor Entity descriptor with property information
     * @param string $domainClass Fully qualified domain class name
     * @param string $protoClass Fully qualified proto class name
     * @throws \RuntimeException If method generation fails
     */
    private function addExtractMethod(
        ClassType $class,
        EntityDescriptor $descriptor,
        string $domainClass,
        string $protoClass,
    ): void {
        $domainShort = $this->getShortClassName($domainClass);
        $protoShort = $this->getShortClassName($protoClass);

        $method = $class->addMethod('extract');
        $method->setReturnType($protoShort);
        $method->addComment("Convert {$domainShort} entity to {$protoShort} message.");
        $method->addComment('');
        $method->addComment("@param {$domainShort} \$entity Domain entity");
        $method->addComment("@return {$protoShort} Proto message");
        $method->addComment('@throws \\RuntimeException If conversion fails due to missing or invalid data');

        $method->addParameter('entity')
            ->setType($domainShort);

        // Add helper method for nested object converters
        $this->addNestedConverterMethod($class);

        $body = "try {\n";
        $body .= "    \$proto = new {$protoShort}();\n\n";

        // Map properties with error handling
        foreach ($descriptor->getProperties() as $property) {
            $propName = $property->name;
            $getter = 'get' . ucfirst($propName);
            $setterName = $this->camelToSnake($propName);
            $setter = 'set' . ucfirst($setterName);

            // Determine PHP type based on configuration or default
            $phpType = $property->type;
            if (!empty($property->protoType)) {
                $phpType = $this->config->mapType($property->protoType, $phpType);
            }

            // Add error handling for property conversion
            $body .= "    // Convert {$propName} property\n";
            $body .= "    try {\n";

            // Improved type handling with null checks and conversions based on type
            if ($phpType === '\DateTime' || $phpType === 'DateTime' || $phpType === '\DateTimeInterface') {
                $body .= "        \$dateValue = \$entity->{$getter}();\n";
                $body .= "        if (\$dateValue !== null) {\n";
                $body .= "            \$proto->{$setter}(\$dateValue->format('c'));\n";
                $body .= "        }\n";
            } elseif ($phpType === 'array' || $property->repeated) {
                $body .= "        \$arrayValue = \$entity->{$getter}();\n";
                $body .= "        if (\$arrayValue !== null) {\n";
                $body .= "            \$proto->{$setter}(\$arrayValue);\n";
                $body .= "        }\n";
            } elseif (str_contains($phpType, '\\') && $phpType !== '\DateTime' && $phpType !== '\DateTimeInterface') {
                // Assume this is a complex nested object type
                $body .= "        \$nestedObject = \$entity->{$getter}();\n";
                $body .= "        if (\$nestedObject !== null) {\n";
                $body .= "            // Handle nested object conversion\n";
                $body .= "            try {\n";
                $body .= "                \$converter = \$this->getNestedConverter(\$nestedObject::class);\n";
                $body .= "                if (\$converter) {\n";
                $body .= "                    \$proto->{$setter}(\$converter->extract(\$nestedObject));\n";
                $body .= "                } else {\n";
                $body .= "                    \$proto->{$setter}(\$nestedObject); // Fallback to direct use if no converter\n";
                $body .= "                }\n";
                $body .= "            } catch (\\Throwable \$e) {\n";
                $body .= "                // Fallback to direct property if conversion fails\n";
                $body .= "                \$proto->{$setter}(\$nestedObject);\n";
                $body .= "            }\n";
                $body .= "        }\n";
            } elseif ($property->nullable) {
                $body .= "        \$value = \$entity->{$getter}();\n";
                $body .= "        if (\$value !== null) {\n";

                // Add type conversion if needed
                if ($phpType === 'int' || $phpType === 'integer') {
                    $body .= "            \$proto->{$setter}((int)\$value);\n";
                } elseif ($phpType === 'float' || $phpType === 'double') {
                    $body .= "            \$proto->{$setter}((float)\$value);\n";
                } elseif ($phpType === 'bool' || $phpType === 'boolean') {
                    $body .= "            \$proto->{$setter}((bool)\$value);\n";
                } elseif ($phpType === 'string') {
                    $body .= "            \$proto->{$setter}((string)\$value);\n";
                } else {
                    $body .= "            \$proto->{$setter}(\$value);\n";
                }

                $body .= "        }\n";
            } else {
                // For non-nullable fields with type conversion
                $body .= "        \$value = \$entity->{$getter}();\n";

                if ($phpType === 'int' || $phpType === 'integer') {
                    $body .= "        \$proto->{$setter}((int)\$value);\n";
                } elseif ($phpType === 'float' || $phpType === 'double') {
                    $body .= "        \$proto->{$setter}((float)\$value);\n";
                } elseif ($phpType === 'bool' || $phpType === 'boolean') {
                    $body .= "        \$proto->{$setter}((bool)\$value);\n";
                } elseif ($phpType === 'string') {
                    $body .= "        \$proto->{$setter}((string)\$value);\n";
                } else {
                    $body .= "        \$proto->{$setter}(\$value);\n";
                }
            }

            $body .= "    } catch (\\Throwable \$e) {\n";
            $body .= "        throw new \\RuntimeException('Failed to convert {$propName} property: ' . \$e->getMessage(), 0, \$e);\n";
            $body .= "    }\n\n";
        }

        $body .= "    return \$proto;\n";
        $body .= "} catch (\\Throwable \$e) {\n";
        $body .= "    throw new \\RuntimeException('Failed to extract {$protoShort} message: ' . \$e->getMessage(), 0, \$e);\n";
        $body .= '}';

        $method->setBody($body);
    }

    /**
     * Add a helper method to handle nested object conversion.
     *
     * @param ClassType $class The class to add the method to
     */
    private function addNestedConverterMethod(ClassType $class): void
    {
        if ($class->hasMethod('getNestedConverter')) {
            return;
        }

        $method = $class->addMethod('getNestedConverter');
        $method->setPrivate();
        $method->addParameter('className')
            ->setType('string');
        $method->setReturnType('?object');
        $method->addComment('Get a converter for a nested object type.');
        $method->addComment('');
        $method->addComment('@param string $className The class name to get a converter for');
        $method->addComment('@return object|null A converter object or null if none found');

        $body = "// This is a stub method for handling nested object conversion\n";
        $body .= "// In a real implementation, this would look up appropriate converters\n";
        $body .= "// based on the class name or create them dynamically\n";
        $body .= "return null;\n";

        $method->setBody($body);
    }

    private function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
    }

    /**
     * Convert camelCase to snake_case with improved handling of special cases.
     *
     * @param string $input The camelCase string to convert
     * @return string The converted snake_case string
     */
    private function camelToSnake(string $input): string
    {
        if (empty($input)) {
            return '';
        }

        // Special case handling for common abbreviations
        $abbreviations = [
            'ID' => 'id',
            'HTTP' => 'http',
            'URL' => 'url',
            'URI' => 'uri',
            'API' => 'api',
            'UUID' => 'uuid',
            'JSON' => 'json',
            'XML' => 'xml',
        ];

        // First replace common abbreviations
        foreach ($abbreviations as $abbr => $replacement) {
            $pattern = '/(?<!^)' . $abbr . '(?=[A-Z][a-z]|$)/';
            $input = preg_replace($pattern, '_' . $replacement, $input) ?? $input;

            // Handle abbreviation at the beginning of the string
            if (str_starts_with($input, $abbr) && \strlen($input) > \strlen($abbr)) {
                $input = $replacement . substr($input, \strlen($abbr));
            }
        }

        // Handle camelCase to snake_case conversion (handles both UpperCamelCase and lowerCamelCase)
        $result = $input;

        // Handle ID patterns
        $result = preg_replace('/([a-z])Id$/', '$1_id', $result) ?? $result;
        $result = preg_replace('/([a-z])Id([A-Z])/', '$1_id_$2', $result) ?? $result;

        // General camelCase to snake_case conversion
        $result = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $result) ?? $result;

        // Handle consecutive uppercase letters (like APIKey -> api_key)
        $result = preg_replace('/([A-Z])([A-Z][a-z])/', '$1_$2', $result) ?? $result;

        return strtolower($result);
    }
}
