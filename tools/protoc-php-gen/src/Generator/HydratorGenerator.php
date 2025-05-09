<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Model\EntityDescriptor;

/**
 * Generator for entity-specific hydrators.
 */
final readonly class HydratorGenerator implements Generator
{
    private PsrPrinter $printer;

    public function __construct(
        private GeneratorConfig $config,
    ) {
        $this->printer = new PsrPrinter();
    }

    public function generate(EntityDescriptor $descriptor): array
    {
        // Skip generation if hydrators are disabled
        if (!$this->config->shouldGenerateHydrators()) {
            return [];
        }

        $file = new PhpFile();
        $file->setStrictTypes();

        $entityNamespace = $this->config->getNamespace() . '\Domain';
        $hydratorNamespace = $this->config->getNamespace() . '\Infrastructure\Hydrator';

        $namespace = $file->addNamespace($hydratorNamespace);
        $namespace->addUse("{$entityNamespace}\\{$descriptor->getName()}");
        $namespace->addUse('App\Infrastructure\Hydrator\TypedHydrator');

        // Create hydrator class
        $class = $namespace->addClass($descriptor->getName() . 'Hydrator');
        $class->setFinal(true)
              ->addImplement('App\Infrastructure\Hydrator\TypedHydrator');

        $class->addComment("Hydrator for {$descriptor->getName()} entity");

        // Add getEntityClass method
        $getEntityClass = $class->addMethod('getEntityClass')
            ->setReturnType('string')
            ->setBody("return {$descriptor->getName()}::class;");

        $getEntityClass->addComment('Get the entity class this hydrator can handle');
        $getEntityClass->addComment('@return string');

        // Add hydrate method
        $hydrateMethod = $class->addMethod('hydrate')
            ->setReturnType("{$entityNamespace}\\{$descriptor->getName()}");

        $dataParam = $hydrateMethod->addParameter('data')
            ->setType('array');

        $hydrateMethod->addComment('Create an entity from array data');
        $hydrateMethod->addComment('@param array $data');
        $hydrateMethod->addComment("@return {$descriptor->getName()}");

        // Add preparation code for handling JSON fields
        $hasJsonFields = false;
        foreach ($descriptor->getProperties() as $property) {
            if ($property->isJson) {
                $hasJsonFields = true;
                break;
            }
        }

        // Hydration code
        $hydrateBody = '';

        // Add JSON decode logic if needed
        if ($hasJsonFields) {
            $hydrateBody .= "// Process JSON fields\n";
            $hydrateBody .= "\$processedData = \$data;\n\n";

            foreach ($descriptor->getProperties() as $property) {
                if ($property->isJson) {
                    $hydrateBody .= "// Decode JSON for {$property->name}\n";
                    $hydrateBody .= "if (isset(\$data['{$property->getColumnName()}']) && is_string(\$data['{$property->getColumnName()}'])) {\n";
                    $hydrateBody .= "    \$processedData['{$property->getColumnName()}'] = json_decode(\$data['{$property->getColumnName()}'], true);\n";
                    $hydrateBody .= "}\n\n";
                }
            }
        } else {
            $hydrateBody .= "\$processedData = \$data;\n\n";
        }

        $hydrateBody .= "return new {$descriptor->getName()}(\n";
        foreach ($descriptor->getProperties() as $property) {
            // Skip ignored properties
            if ($property->ignore) {
                continue;
            }

            $defaultValue = $property->nullable ? 'null'
                : match ($property->type) {
                    'string' => "''",
                    'int', 'float' => '0',
                    'bool' => 'false',
                    'array' => '[]',
                    default => 'null',
                };

            $hydrateBody .= "    {$property->name}: \$processedData['{$property->getColumnName()}'] ?? {$defaultValue},\n";
        }
        $hydrateBody .= ');';
        $hydrateMethod->setBody($hydrateBody);

        // Add extract method
        $extractMethod = $class->addMethod('extract')
            ->setReturnType('array');

        $entityParam = $extractMethod->addParameter('entity')
            ->setType("{$entityNamespace}\\{$descriptor->getName()}");

        $extractMethod->addComment('Extract data from entity to array');
        $extractMethod->addComment("@param {$descriptor->getName()} \$entity");
        $extractMethod->addComment('@return array');

        // Check if we have any JSON fields
        $hasJsonFields = false;
        foreach ($descriptor->getProperties() as $property) {
            if ($property->isJson) {
                $hasJsonFields = true;
                break;
            }
        }

        // Start building the extract method body
        $extractBody = '';

        if ($hasJsonFields) {
            $extractBody .= "\$data = [\n";
            foreach ($descriptor->getProperties() as $property) {
                // Skip ignored properties
                if ($property->ignore) {
                    continue;
                }
                $extractBody .= "    '{$property->getColumnName()}' => \$entity->{$property->name},\n";
            }
            $extractBody .= "];\n\n";

            $extractBody .= "// Encode JSON fields\n";
            foreach ($descriptor->getProperties() as $property) {
                if ($property->isJson) {
                    $extractBody .= "if (isset(\$data['{$property->getColumnName()}'])) {\n";
                    $extractBody .= "    \$data['{$property->getColumnName()}'] = json_encode(\$data['{$property->getColumnName()}']);\n";
                    $extractBody .= "}\n";
                }
            }

            $extractBody .= "\nreturn \$data;";
        } else {
            $extractBody = "return [\n";
            foreach ($descriptor->getProperties() as $property) {
                // Skip ignored properties
                if ($property->ignore) {
                    continue;
                }
                $extractBody .= "    '{$property->getColumnName()}' => \$entity->{$property->name},\n";
            }
            $extractBody .= '];';
        }

        $extractMethod->setBody($extractBody);

        // Generate code
        $content = $this->printer->printFile($file);

        $filePath = $this->config->getOutputDir() . '/Infrastructure/Hydrator/'
                  . $descriptor->getName() . 'Hydrator.php';

        return [new GeneratedFile($filePath, $content)];
    }
}
