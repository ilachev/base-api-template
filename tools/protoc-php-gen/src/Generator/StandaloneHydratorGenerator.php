<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Model\ClassMapping;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\FieldMapping;

/**
 * Generator for hydrator classes between proto and domain entities.
 * This is a standalone version that does not require any configuration.
 */
final class StandaloneHydratorGenerator implements Generator
{
    private PsrPrinter $printer;

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

        // Create the hydrator class name
        $hydratorClassName = $domainShortClass . 'ProtoHydrator';

        // Create the file
        $file = new PhpFile();
        $file->setStrictTypes();

        // Create namespace
        $ns = $file->addNamespace($namespace);

        // Add imports
        $ns->addUse($domainClass);
        $ns->addUse($protoClass);

        // Create the class
        $class = $ns->addClass($hydratorClassName);
        $class->setFinal(true);

        // Add hydrate method (proto -> domain)
        $this->addHydrateMethod($class, $mapping);

        // Add extract method (domain -> proto)
        $this->addExtractMethod($class, $mapping);

        // Generate file path
        $outputPath = $outputDir . '/' . $hydratorClassName . '.php';

        // Write the file
        $content = $this->printer->printFile($file);
        file_put_contents($outputPath, $content);

        return $outputPath;
    }

    /**
     * Add the hydrate method to convert proto to domain entity.
     */
    private function addHydrateMethod(ClassType $class, ClassMapping $mapping): void
    {
        $domainClass = $mapping->getDomainClass();
        $protoClass = $mapping->getProtoClass();
        $domainShortClass = $this->getShortClassName($domainClass);
        $protoShortClass = $this->getShortClassName($protoClass);

        $method = $class->addMethod('hydrate');
        $method->setReturnType($domainShortClass);
        $method->addComment("Convert {$protoShortClass} message to {$domainShortClass} entity.");
        $method->addComment('');
        $method->addComment("@param {$protoShortClass} \$proto Proto message");
        $method->addComment("@return {$domainShortClass} Domain entity");

        $method->addParameter('proto')
            ->setType($protoShortClass);

        $body = "\$entity = new {$domainShortClass}();\n\n";

        foreach ($mapping->getFieldMappings() as $fieldMapping) {
            $body .= $this->generateHydrationCode($fieldMapping);
        }

        $body .= "\nreturn \$entity;";
        $method->setBody($body);
    }

    /**
     * Add the extract method to convert domain entity to proto.
     */
    private function addExtractMethod(ClassType $class, ClassMapping $mapping): void
    {
        $domainClass = $mapping->getDomainClass();
        $protoClass = $mapping->getProtoClass();
        $domainShortClass = $this->getShortClassName($domainClass);
        $protoShortClass = $this->getShortClassName($protoClass);

        $method = $class->addMethod('extract');
        $method->setReturnType($protoShortClass);
        $method->addComment("Convert {$domainShortClass} entity to {$protoShortClass} message.");
        $method->addComment('');
        $method->addComment("@param {$domainShortClass} \$entity Domain entity");
        $method->addComment("@return {$protoShortClass} Proto message");

        $method->addParameter('entity')
            ->setType($domainShortClass);

        $body = "\$proto = new {$protoShortClass}();\n\n";

        foreach ($mapping->getFieldMappings() as $fieldMapping) {
            $body .= $this->generateExtractionCode($fieldMapping);
        }

        $body .= "\nreturn \$proto;";
        $method->setBody($body);
    }

    /**
     * Generate code for hydrating a single field.
     */
    private function generateHydrationCode(FieldMapping $mapping): string
    {
        $domainProperty = $mapping->getDomainProperty();
        $protoField = $mapping->getProtoField();
        $type = $mapping->getType();
        $transformer = $mapping->getTransformer();

        $getterMethod = 'get' . ucfirst($protoField);
        $setterMethod = 'set' . ucfirst($domainProperty);

        if ($transformer !== null) {
            return "\$entity->{$setterMethod}(\$this->{$transformer}(\$proto->{$getterMethod}()));\n";
        }

        switch ($type) {
            case 'json':
                return "\$entity->{$setterMethod}(json_decode(\$proto->{$getterMethod}(), true));\n";

            case 'datetime':
                return "\$entity->{$setterMethod}(new \\DateTime(\$proto->{$getterMethod}()));\n";

            case 'enum':
                $options = $mapping->getOptions();
                $enumClass = $options['enum_class'] ?? 'Enum';

                return "\$entity->{$setterMethod}({$enumClass}::from(\$proto->{$getterMethod}()));\n";

            default:
                return "\$entity->{$setterMethod}(\$proto->{$getterMethod}());\n";
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

        $getterMethod = 'get' . ucfirst($domainProperty);
        $setterMethod = 'set' . ucfirst($protoField);

        if ($transformer !== null) {
            $inverseTransformer = 'inverse' . ucfirst($transformer);

            return "\$proto->{$setterMethod}(\$this->{$inverseTransformer}(\$entity->{$getterMethod}()));\n";
        }

        switch ($type) {
            case 'json':
                return "\$proto->{$setterMethod}(json_encode(\$entity->{$getterMethod}()));\n";

            case 'datetime':
                return "\$proto->{$setterMethod}(\$entity->{$getterMethod}()->format('c'));\n";

            case 'enum':
                return "\$proto->{$setterMethod}(\$entity->{$getterMethod}()->value);\n";

            default:
                return "\$proto->{$setterMethod}(\$entity->{$getterMethod}());\n";
        }
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
