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

        $hydrateBody = "return new {$descriptor->getName()}(\n";
        foreach ($descriptor->getProperties() as $property) {
            $defaultValue = $property->nullable ? 'null'
                : match ($property->type) {
                    'string' => "''",
                    'int', 'float' => '0',
                    'bool' => 'false',
                    'array' => '[]',
                    default => 'null',
                };

            $hydrateBody .= "    {$property->name}: \$data['{$property->getColumnName()}'] ?? {$defaultValue},\n";
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

        $extractBody = "return [\n";
        foreach ($descriptor->getProperties() as $property) {
            $extractBody .= "    '{$property->getColumnName()}' => \$entity->{$property->name},\n";
        }
        $extractBody .= '];';
        $extractMethod->setBody($extractBody);

        // Generate code
        $content = $this->printer->printFile($file);

        $filePath = $this->config->getOutputDir() . '/Infrastructure/Hydrator/'
                  . $descriptor->getName() . 'Hydrator.php';

        return [new GeneratedFile($filePath, $content)];
    }
}
