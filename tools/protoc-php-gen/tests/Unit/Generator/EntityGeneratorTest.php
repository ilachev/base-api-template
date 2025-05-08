<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\EntityGenerator;
use ProtoPhpGen\Generator\GeneratedFile;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;

/**
 * @covers \ProtoPhpGen\Generator\EntityGenerator
 */
final class EntityGeneratorTest extends TestCase
{
    private GeneratorConfig $config;
    private EntityGenerator $generator;

    protected function setUp(): void
    {
        $this->config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            entityInterface: 'App\\Domain\\Entity',
        );
        $this->generator = new EntityGenerator($this->config);
    }

    /**
     * Тест генерации простой сущности.
     */
    public function testGenerateSimpleEntity(): void
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
        self::assertInstanceOf(GeneratedFile::class, $files[0]);
        
        $filePath = 'gen/Domain/User.php';
        self::assertSame($filePath, $files[0]->getName());
        
        $content = $files[0]->getContent();

        // Проверяем основные элементы сгенерированного кода
        self::assertStringContainsString('declare(strict_types=1);', $content);
        self::assertStringContainsString('namespace App\\Gen\\Domain;', $content);
        self::assertStringContainsString('use App\\Domain\\Entity;', $content);
        self::assertStringContainsString('final readonly class User implements \\Entity', $content);
        self::assertStringContainsString('public int $id', $content);
        self::assertStringContainsString('public string $name', $content);
        self::assertStringContainsString('public function __construct(', $content);
        self::assertStringContainsString('int $id', $content);
        self::assertStringContainsString('string $name', $content);
    }

    /**
     * Тест генерации сущности с nullable свойствами.
     */
    public function testGenerateEntityWithNullableProperties(): void
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
            name: 'email',
            type: 'string',
            nullable: true,
            columnName: 'email',
        ));

        // Act
        $files = $this->generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);
        $content = $files[0]->getContent();

        // Проверяем nullable свойства
        self::assertStringContainsString('public ?string $email', $content);
        self::assertStringContainsString('?string $email = null', $content);
    }
}
