<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\StandaloneHydratorGenerator;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;

/**
 * @covers \ProtoPhpGen\Generator\StandaloneHydratorGenerator
 */
final class StandaloneHydratorGeneratorTest extends TestCase
{
    private GeneratorConfig $config;
    private StandaloneHydratorGenerator $generator;

    protected function setUp(): void
    {
        $this->config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            standaloneMode: true,
        );
        $this->generator = new StandaloneHydratorGenerator($this->config);
    }

    /**
     * Test generation of standalone hydrator.
     */
    public function testGenerate(): void
    {
        // Arrange
        $descriptor = new EntityDescriptor(
            name: 'Product',
            namespace: 'App\\Domain\\Product',
            tableName: 'products',
            primaryKey: 'id',
        );

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'id',
            type: 'int',
            nullable: false,
            columnName: 'id',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'title',
            type: 'string',
            nullable: false,
            columnName: 'title',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'price',
            type: 'float',
            nullable: false,
            columnName: 'price',
        ));

        // Act
        $files = $this->generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);
        
        $content = $files[0]->getContent();

        // Check for standalone-specific elements
        self::assertStringContainsString('class ProductHydrator', $content);
        self::assertStringContainsString('public function hydrate(array $data): Product', $content);
        self::assertStringContainsString('public function extract(Product $entity): array', $content);
        
        // Standalone hydrators don't implement any interface
        self::assertStringNotContainsString('implements', $content);
        
        // Check hydration and extraction methods
        self::assertStringContainsString("'id' => \$entity->getId(),", $content);
        self::assertStringContainsString("'title' => \$entity->getTitle(),", $content);
        self::assertStringContainsString("'price' => \$entity->getPrice(),", $content);
    }

    /**
     * Test generation of standalone hydrator with complex types.
     */
    public function testGenerateWithComplexTypes(): void
    {
        // Arrange
        $descriptor = new EntityDescriptor(
            name: 'Order',
            namespace: 'App\\Domain\\Order',
            tableName: 'orders',
            primaryKey: 'id',
        );

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'id',
            type: 'int',
            nullable: false,
            columnName: 'id',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'items',
            type: 'array',
            nullable: false,
            columnName: 'items',
            repeated: true,
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'createdAt',
            type: '\\DateTime',
            nullable: false,
            columnName: 'created_at',
        ));

        // Act
        $files = $this->generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);
        
        $content = $files[0]->getContent();
        
        // Check handling of complex types
        self::assertStringContainsString('array $items', $content);
        self::assertStringContainsString('\\DateTime $createdAt', $content);
    }

    /**
     * Test standalone hydrator generation disabled.
     */
    public function testGenerateDisabled(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            standaloneMode: false,
        );
        $generator = new StandaloneHydratorGenerator($config);

        $descriptor = new EntityDescriptor(
            name: 'Product',
            namespace: 'App\\Domain\\Product',
            tableName: 'products',
            primaryKey: 'id',
        );

        // Act
        $files = $generator->generate($descriptor);

        // Assert - should be empty since standalone mode is disabled
        self::assertCount(0, $files);
    }

    /**
     * Test using custom output pattern.
     */
    public function testCustomOutputPath(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            standaloneMode: true,
            outputPattern: '{outputDir}/Standalone/{className}.php',
        );
        $generator = new StandaloneHydratorGenerator($config);

        $descriptor = new EntityDescriptor(
            name: 'Order',
            namespace: 'App\\Domain\\Order',
            tableName: 'orders',
            primaryKey: 'id',
        );

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'id',
            type: 'int',
            nullable: false,
            columnName: 'id',
        ));

        // Act
        $files = $generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);
        
        // Check custom output path
        $filePath = 'gen/Standalone/OrderHydrator.php';
        self::assertSame($filePath, $files[0]->getName());
    }
}