<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Model\ClassMapping;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\FieldMapping;

/**
 * Generator for mapper classes between proto and domain entities.
 * Creates bi-directional mappers that can convert data in both directions.
 */
final class ProtoDomainMapperGenerator implements Generator
{
    private PsrPrinter $printer;

    private string $domainAlias = '';

    private string $protoAlias = '';

    public function __construct()
    {
        $this->printer = new PsrPrinter();
    }

    /**
     * Generate code files from entity descriptor.
     *
     * @return GeneratedFile[]
     */
    public function generate(EntityDescriptor $descriptor): array
    {
        // For compatibility with the Generator interface
        return [];
    }

    /**
     * Generate hydrator class for a class mapping.
     */
    public function generateFromMapping(ClassMapping $mapping, string $outputDir, string $namespace = 'App\Infrastructure\Hydrator'): string
    {
        $domainClass = $mapping->getDomainClass();
        $protoClass = $mapping->getProtoClass();

        // Get short class names
        $domainShortClass = $this->getShortClassName($domainClass);
        $protoShortClass = $this->getShortClassName($protoClass);

        // Create the mapper class name
        $mapperClassName = $domainShortClass . 'ProtoMapper';

        // Create the file
        $file = new PhpFile();
        $file->setStrictTypes();

        // Add file header comment
        $file->addComment('This file is auto-generated. DO NOT EDIT.');
        $file->addComment('');
        $file->addComment('Generated by protoc-php-gen');

        // Create namespace
        $ns = $file->addNamespace($namespace);

        // Check if domain and proto class are the same or have the same short name
        $isDomainAndProtoSame = $domainClass === $protoClass;
        $isSameShortName = ($domainShortClass === $protoShortClass) && !$isDomainAndProtoSame;

        // Define unique aliases for domain and proto classes
        $domainAlias = 'Domain' . $domainShortClass;
        $protoAlias = 'Proto' . $protoShortClass;

        // If the proto and domain classes are in different namespaces but have the same short name,
        // we need more distinctive aliases based on their namespaces
        if ($isSameShortName) {
            // Extract namespace parts to make unique aliases
            $domainNsParts = explode('\\', $domainClass);
            $protoNsParts = explode('\\', $protoClass);

            // Remove the last part (class name) and get the last namespace segment
            array_pop($domainNsParts);
            array_pop($protoNsParts);

            $domainNsHint = !empty($domainNsParts) ? end($domainNsParts) : 'Domain';
            $protoNsHint = !empty($protoNsParts) ? end($protoNsParts) : 'Proto';

            // Create more specific aliases
            $domainAlias = $domainNsHint . $domainShortClass;
            $protoAlias = $protoNsHint . $protoShortClass;
        }

        // Add imports with clear aliases
        $ns->addUse($domainClass, $domainAlias);
        $ns->addUse($protoClass, $protoAlias);

        // Store aliases for later use in the code
        $this->domainAlias = $domainAlias;
        $this->protoAlias = $protoAlias;

        // Create the class
        $class = $ns->addClass($mapperClassName);
        $class->setFinal(true);

        // Add hydrate method (proto -> domain)
        $this->addHydrateMethod($class, $mapping, $ns);

        // Add extract method (domain -> proto)
        $this->addExtractMethod($class, $mapping, $ns);

        // Generate file path
        $outputPath = $outputDir . '/' . $mapperClassName . '.php';

        // Write the file
        $content = $this->printer->printFile($file);
        file_put_contents($outputPath, $content);

        return $outputPath;
    }

    /**
     * Add the hydrate method to convert proto to domain entity.
     */
    private function addHydrateMethod(ClassType $class, ClassMapping $mapping, PhpNamespace $namespace): void
    {
        $domainClass = $mapping->getDomainClass();
        $protoClass = $mapping->getProtoClass();
        $domainShortClass = $this->getShortClassName($domainClass);
        $protoShortClass = $this->getShortClassName($protoClass);

        // Use the aliases stored at the class level
        $domainTypeHint = $this->domainAlias;
        $protoTypeHint = $this->protoAlias;

        // Создаем метод hydrate вручную, используя simplifyName для типов
        $method = $class->addMethod('hydrate');

        // Remove any leading backslashes for PhpGenerator types
        $returnType = ltrim($domainTypeHint, '\\');
        $paramType = ltrim($protoTypeHint, '\\');

        // For method declaration we use the simplified type
        $method->setReturnType($returnType);
        $method->addComment("Convert {$protoShortClass} message to {$domainShortClass} entity.");
        $method->addComment('');
        $method->addComment("@param {$paramType} \$proto Proto message");
        $method->addComment("@return {$returnType} Domain entity");

        // Add parameter with the simplified type
        $param = $method->addParameter('proto');
        $param->setType($paramType);

        // Уберем обратные слеши из сигнатуры метода в сгенерированном коде
        $method->addBody('');  // Пустая строка для лучшего форматирования

        // Analyze the domain class constructor using reflection
        if (!class_exists($domainClass)) {
            // Skip constructor analysis if class doesn't exist (for testing)
            $constructorParams = [];
        } else {
            $constructorParams = $this->getConstructorParams($domainClass);
        }
        $propertyMap = [];

        foreach ($mapping->getFieldMappings() as $fieldMapping) {
            $propertyMap[$fieldMapping->getDomainProperty()] = $fieldMapping;
        }

        // Generate the constructor call with parameters
        $constructorArgs = [];
        foreach ($constructorParams as $param) {
            $paramName = $param->getName();
            if (isset($propertyMap[$paramName])) {
                $fieldMapping = $propertyMap[$paramName];
                $constructorArgs[$paramName] = $this->generateHydrationValueCode($fieldMapping);
            } else {
                // If parameter doesn't have a mapping, use default value if available
                if ($param->isDefaultValueAvailable()) {
                    $defaultValue = $param->getDefaultValue();
                    if (\is_string($defaultValue)) {
                        $constructorArgs[$paramName] = "'" . addslashes($defaultValue) . "'";
                    } elseif ($defaultValue === null) {
                        $constructorArgs[$paramName] = 'null';
                    } elseif (\is_bool($defaultValue)) {
                        $constructorArgs[$paramName] = $defaultValue ? 'true' : 'false';
                    } elseif (\is_array($defaultValue)) {
                        $constructorArgs[$paramName] = '[]'; // Simplified array default
                    } else {
                        $constructorArgs[$paramName] = (string) $defaultValue;
                    }
                } else {
                    // If no default value, use null (potentially causes type error)
                    $constructorArgs[$paramName] = 'null';
                }
            }
        }

        // Build constructor call
        $constructorArgsStr = implode(', ', array_map(
            static fn($key, $value) => "\n    {$value}, // {$key}",
            array_keys($constructorArgs),
            array_values($constructorArgs),
        ));

        // Generate method body - use proper type hint without leading backslashes
        $body = "return new {$domainTypeHint}({$constructorArgsStr}\n);";

        // Заменяем стандартный метод генерации на наш
        $method->setBody($body);
    }

    /**
     * Add the extract method to convert domain entity to proto.
     */
    private function addExtractMethod(ClassType $class, ClassMapping $mapping, PhpNamespace $namespace): void
    {
        $domainClass = $mapping->getDomainClass();
        $protoClass = $mapping->getProtoClass();
        $domainShortClass = $this->getShortClassName($domainClass);
        $protoShortClass = $this->getShortClassName($protoClass);

        // Use the aliases stored at the class level
        $domainTypeHint = $this->domainAlias;
        $protoTypeHint = $this->protoAlias;

        // Создаем метод extract вручную, используя simplifyName для типов
        $method = $class->addMethod('extract');

        // Remove any leading backslashes for PhpGenerator types
        $returnType = ltrim($protoTypeHint, '\\');
        $paramType = ltrim($domainTypeHint, '\\');

        // For method declaration we use the simplified type
        $method->setReturnType($returnType);
        $method->addComment("Convert {$domainShortClass} entity to {$protoShortClass} message.");
        $method->addComment('');
        $method->addComment("@param {$paramType} \$entity Domain entity");
        $method->addComment("@return {$returnType} Proto message");

        $param = $method->addParameter('entity');
        $param->setType($paramType);

        // Уберем обратные слеши из сигнатуры метода в сгенерированном коде
        $method->addBody('');  // Пустая строка для лучшего форматирования

        // Use proper type hint without leading backslashes
        $body = "\$proto = new {$protoTypeHint}();\n\n";

        foreach ($mapping->getFieldMappings() as $fieldMapping) {
            $body .= $this->generateExtractionCode($fieldMapping);
        }

        $body .= "\nreturn \$proto;";
        $method->setBody($body);
    }

    /**
     * Generate code for retrieving the value for a constructor parameter.
     */
    private function generateHydrationValueCode(FieldMapping $mapping): string
    {
        $protoField = $mapping->getProtoField();
        $type = $mapping->getType();
        $transformer = $mapping->getTransformer();
        $options = $mapping->getOptions();

        // Convert snake_case to camelCase for proto getters
        $getterMethod = 'get' . $this->snakeToCamelCase($protoField, true);

        if ($transformer !== null) {
            return "\$this->{$transformer}(\$proto->{$getterMethod}())";
        }

        switch ($type) {
            case 'json':
                // Простое общее решение: для всех JSON полей используем json_decode
                return "json_decode(\$proto->{$getterMethod}(), true)";

            case 'datetime':
                return "new \\DateTime('@' . \$proto->{$getterMethod}())";

            case 'enum':
                $enumClass = $options['enum_class'] ?? 'Enum';

                return "{$enumClass}::from(\$proto->{$getterMethod}())";

            default:
                return "\$proto->{$getterMethod}()";
        }
    }

    /**
     * Generate code for extracting a single field.
     */
    private function generateExtractionCode(FieldMapping $mapping): string
    {
        $domainProperty = $mapping->getDomainProperty();
        $protoField = $mapping->getProtoField();
        $type = $mapping->getType();
        $transformer = $mapping->getTransformer();

        // Proto classes use camelCase setters
        $setterMethod = 'set' . $this->snakeToCamelCase($protoField, true);

        if ($transformer !== null) {
            $inverseTransformer = 'inverse' . ucfirst($transformer);

            return "\$proto->{$setterMethod}(\$this->{$inverseTransformer}(\$entity->{$domainProperty}));\n";
        }

        switch ($type) {
            case 'json':
                // Для объектных полей мы просто кодируем их в JSON
                return "\$proto->{$setterMethod}(\$entity->{$domainProperty} !== null ? json_encode(\$entity->{$domainProperty}) : null);\n";

            case 'datetime':
                return "\$proto->{$setterMethod}(\$entity->{$domainProperty} instanceof \\DateTimeInterface ? \$entity->{$domainProperty}->getTimestamp() : 0);\n";

            case 'enum':
                return "\$proto->{$setterMethod}(\$entity->{$domainProperty}->value);\n";

            default:
                return "\$proto->{$setterMethod}(\$entity->{$domainProperty});\n";
        }
    }

    /**
     * Get constructor parameters for a class.
     *
     * @param class-string $className Fully qualified class name
     * @return \ReflectionParameter[] Constructor parameters
     */
    private function getConstructorParams(string $className): array
    {
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        return $constructor->getParameters();
    }

    /**
     * Convert snake_case to camelCase.
     *
     * @param string $input Input string in snake_case
     * @param bool $firstUpper Whether the first letter should be uppercase
     * @return string Resulting camelCase string
     */
    private function snakeToCamelCase(string $input, bool $firstUpper = false): string
    {
        $output = str_replace('_', '', ucwords($input, '_'));

        if (!$firstUpper) {
            $output = lcfirst($output);
        }

        return $output;
    }

    /**
     * Get short class name from a fully qualified class name.
     */
    private function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
    }
}
