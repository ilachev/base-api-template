<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Model\EntityDescriptor;

/**
 * Generator for entity hydrators.
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

        $className = $descriptor->getName() . 'Hydrator';
        $class = $namespace->addClass($className);
        $class->setFinal(true)
              ->addImplement($this->config->getHydratorInterface() ?: 'App\Infrastructure\Hydrator\TypedHydrator');

        // Add getEntityClass method
        $getEntityClassMethod = $class->addMethod('getEntityClass');
        $getEntityClassMethod->setReturnType('string');
        $getEntityClassMethod->setBody("return {$descriptor->getName()}::class;");

        // Create properties for use in the hydrate/extract methods
        $properties = [];
        foreach ($descriptor->getProperties() as $property) {
            $properties[] = $property;
        }

        // Add hydrate method
        $hydrate = $class->addMethod('hydrate');
        $hydrate->setReturnType("{$entityNamespace}\\{$descriptor->getName()}");
        $hydrate->addParameter('data')->setType('array');

        $hydrateBody = <<<EOT
// Process data before creating entity
\$processedData = \$data;

return new {$descriptor->getName()}(
EOT;

        foreach ($properties as $property) {
            $defaultValue = 'null';
            if (!$property->nullable) {
                switch ($property->type) {
                    case 'int':
                        $defaultValue = '0';
                        break;
                    case 'string':
                        $defaultValue = "''";
                        break;
                    case 'array':
                        $defaultValue = '[]';
                        break;
                    case 'bool':
                        $defaultValue = 'false';
                        break;
                    default:
                        $defaultValue = 'null';
                }
            }

            $hydrateBody .= "\n    {$property->name}: \$processedData['{$property->name}'] ?? {$defaultValue},";
        }

        $hydrateBody .= "\n);";
        $hydrate->setBody($hydrateBody);

        // Add extract method
        $extract = $class->addMethod('extract');
        $extract->setReturnType('array');
        $extract->addParameter('entity')->setType("{$entityNamespace}\\{$descriptor->getName()}");

        $extractBody = "return [";
        foreach ($properties as $property) {
            $extractBody .= "\n    '{$property->name}' => \$entity->{$property->name},";
        }
        $extractBody .= "\n];";

        $extract->setBody($extractBody);

        // Generate code
        $content = $this->printer->printFile($file);
        $filePath = $this->config->getOutputDir() . '/Infrastructure/Hydrator/'
                  . $className . '.php';

        return [new GeneratedFile($filePath, $content)];
    }
}
