<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\RepositoryGenerator;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;

/**
 * @covers \ProtoPhpGen\Generator\RepositoryGenerator
 */
final class RepositoryGeneratorTest extends TestCase
{
    private GeneratorConfig $config;
    private RepositoryGenerator $generator;

    protected function setUp(): void
    {
        $this->config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            entityInterface: 'App\\Domain\\Entity',
            generateRepositories: true,
        );
        $this->generator = new RepositoryGenerator($this->config);
    }

    /**
     * Тест генерации репозитория для сущности.
     */
    public function testGenerateRepository(): void
    {
        // Arrange
        $descriptor = new EntityDescriptor(
            name: 'User',
            namespace: 'App\\Domain\\User',
            tableName: 'users',
            primaryKey: 'id',
        );

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'id',
            type: 'int',
            nullable: false,
            columnName: 'id',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'name',
            type: 'string',
            nullable: false,
            columnName: 'name',
        ));

        // Act
        $files = $this->generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);
        
        $filePath = 'gen/Infrastructure/Storage/PostgreSQLUserRepository.php';
        self::assertSame($filePath, $files[0]->getName());
        
        $content = $files[0]->getContent();

        // Проверяем основные элементы сгенерированного кода
        self::assertStringContainsString('declare(strict_types=1);', $content);
        self::assertStringContainsString('namespace App\\Gen\\Infrastructure\\Storage;', $content);
        self::assertStringContainsString('use App\\Gen\\Domain\\User;', $content);
        self::assertStringContainsString('use App\\Infrastructure\\Storage\\Repository\\AbstractRepository;', $content);
        
        // Проверяем интерфейс репозитория
        self::assertStringContainsString('interface UserRepository', $content);
        self::assertStringContainsString('function findById($id)', $content);
        self::assertStringContainsString('function findAll(): array;', $content);
        self::assertStringContainsString('function save', $content);
        self::assertStringContainsString('function delete', $content);
        
        // Проверяем класс PostgreSQL репозитория
        self::assertStringContainsString('final class PostgreSQLUserRepository extends AbstractRepository implements UserRepository', $content);
        self::assertStringContainsString('public function __construct', $content);
        self::assertStringContainsString('protected function getTableName(): string', $content);
        self::assertStringContainsString("return 'users';", $content);
        self::assertStringContainsString('protected function getEntityClass(): string', $content);
        self::assertStringContainsString('return User::class;', $content);
        
        // Проверяем реализацию методов
        self::assertStringContainsString('public function findById($id)', $content);
        self::assertStringContainsString("'id' => \$id,", $content);
        self::assertStringContainsString('public function findAll(): array', $content);
        self::assertStringContainsString('return $this->find();', $content);
        self::assertStringContainsString('public function save', $content);
        self::assertStringContainsString('return $this->saveEntity($entity);', $content);
        self::assertStringContainsString('public function delete', $content);
        self::assertStringContainsString('return $this->deleteEntity($entity);', $content);
    }

    /**
     * Тест отключения генерации репозиториев.
     */
    public function testGenerateRepositoryDisabled(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            entityInterface: 'App\\Domain\\Entity',
            generateRepositories: false,
        );
        $generator = new RepositoryGenerator($config);

        $descriptor = new EntityDescriptor(
            name: 'User',
            namespace: 'App\\Domain\\User',
            tableName: 'users',
            primaryKey: 'id',
        );

        // Act
        $files = $generator->generate($descriptor);

        // Assert
        // Если генерация репозиториев отключена, то должен быть пустой массив
        self::assertCount(0, $files);
    }
}
