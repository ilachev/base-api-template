<?php

declare(strict_types=1);

namespace ProtoPhpGen\Generator;

use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Model\EntityDescriptor;

/**
 * Generator for entity repositories.
 */
final readonly class RepositoryGenerator implements Generator
{
    private PsrPrinter $printer;

    public function __construct(
        private GeneratorConfig $config,
    ) {
        $this->printer = new PsrPrinter();
    }

    public function generate(EntityDescriptor $descriptor): array
    {
        // Skip generation if repositories are disabled
        if (!$this->config->shouldGenerateRepositories()) {
            return [];
        }

        $file = new PhpFile();
        $file->setStrictTypes();

        $entityNamespace = $this->config->getNamespace() . '\Domain';
        $repositoryNamespace = $this->config->getNamespace() . '\Infrastructure\Storage';

        $namespace = $file->addNamespace($repositoryNamespace);
        $namespace->addUse("{$entityNamespace}\\{$descriptor->getName()}");
        $namespace->addUse('App\Infrastructure\Storage\Repository\AbstractRepository');
        $namespace->addUse('App\Infrastructure\Hydrator\Hydrator');
        $namespace->addUse('App\Infrastructure\Storage\Storage');

        // Create repository interface
        $interfaceName = $descriptor->getName() . 'Repository';
        $interface = $namespace->addInterface($interfaceName);
        $interface->addComment("Repository interface for {$descriptor->getName()} entity");

        // Add common repository methods to interface
        $interface->addMethod('findById')
            ->setReturnType("?{$descriptor->getName()}")
            ->addParameter('id');

        $interface->addMethod('findAll')
            ->setReturnType('array');

        $interface->addMethod('save')
            ->setReturnType($descriptor->getName())
            ->addParameter('entity')
            ->setType($descriptor->getName());

        $interface->addMethod('delete')
            ->setReturnType('bool')
            ->addParameter('entity')
            ->setType($descriptor->getName());

        // Create PostgreSQL repository implementation
        $postgresClassName = "PostgreSQL{$descriptor->getName()}Repository";
        $postgresClass = $namespace->addClass($postgresClassName);
        $postgresClass->setFinal(true)
            ->setExtends('App\Infrastructure\Storage\Repository\AbstractRepository')
            ->addImplement($interfaceName);

        $postgresClass->addComment("PostgreSQL implementation of {$interfaceName}");

        // Add constructor
        $constructor = $postgresClass->addMethod('__construct');
        $constructor->addParameter('storage')
            ->setType('Storage');
        $constructor->addParameter('hydrator')
            ->setType('Hydrator');
        $constructor->setBody('parent::__construct($storage, $hydrator);');

        // Add getTableName method
        $postgresClass->addMethod('getTableName')
            ->setProtected()
            ->setReturnType('string')
            ->setBody("return '{$descriptor->getTableName()}';");

        // Add getEntityClass method
        $postgresClass->addMethod('getEntityClass')
            ->setProtected()
            ->setReturnType('string')
            ->setBody("return {$descriptor->getName()}::class;");

        // Add findById method
        $findById = $postgresClass->addMethod('findById')
            ->setPublic()
            ->setReturnType("?{$descriptor->getName()}");

        $findById->addParameter('id');

        $findById->setBody(
            "return \$this->findOne([\n"
            . "    '{$descriptor->getPrimaryKey()}' => \$id,\n"
            . ']);',
        );

        // Add findAll method
        $findAll = $postgresClass->addMethod('findAll')
            ->setPublic()
            ->setReturnType('array');

        $findAll->setBody(
            'return $this->find();',
        );

        // Add save method
        $save = $postgresClass->addMethod('save')
            ->setPublic()
            ->setReturnType($descriptor->getName());

        $save->addParameter('entity')
            ->setType($descriptor->getName());

        $save->setBody(
            'return $this->saveEntity($entity);',
        );

        // Add delete method
        $delete = $postgresClass->addMethod('delete')
            ->setPublic()
            ->setReturnType('bool');

        $delete->addParameter('entity')
            ->setType($descriptor->getName());

        $delete->setBody(
            'return $this->deleteEntity($entity);',
        );

        // Generate code
        $content = $this->printer->printFile($file);

        $filePath = $this->config->getOutputDir() . '/Infrastructure/Storage/'
                  . $postgresClassName . '.php';

        return [new GeneratedFile($filePath, $content)];
    }
}
