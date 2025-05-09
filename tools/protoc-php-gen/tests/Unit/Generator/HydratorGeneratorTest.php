<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\HydratorGenerator;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;

/**
 * @covers \ProtoPhpGen\Generator\HydratorGenerator
 */
final class HydratorGeneratorTest extends TestCase
{
    private GeneratorConfig $config;
    private HydratorGenerator $generator;

    protected function setUp(): void
    {
        $this->config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            hydratorInterface: 'App\\Infrastructure\\Hydrator\\TypedHydrator',
            generateHydrators: true,
        );
        $this->generator = new HydratorGenerator($this->config);
    }

    /**
     * Test generation of basic hydrator for entity.
     */
    public function testGenerate(): void
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

        $filePath = 'gen/Infrastructure/Hydrator/UserHydrator.php';
        self::assertSame($filePath, $files[0]->getName());

        $content = $files[0]->getContent();

        // Check generated code main elements
        self::assertStringContainsString('declare(strict_types=1);', $content);
        self::assertStringContainsString('namespace App\\Gen\\Infrastructure\\Hydrator;', $content);
        self::assertStringContainsString('use App\\Gen\\Domain\\User;', $content);
        self::assertStringContainsString('use App\\Infrastructure\\Hydrator\\TypedHydrator;', $content);
        self::assertStringContainsString('final class UserHydrator implements TypedHydrator', $content);
        self::assertStringContainsString('public function getEntityClass(): string', $content);
        self::assertStringContainsString('return User::class;', $content);
        self::assertStringContainsString('public function hydrate(array $data)', $content);
        self::assertStringContainsString('public function extract', $content);

        // Check hydration and extraction methods
        self::assertStringContainsString("id: \$processedData['id'] ?? 0,", $content);
        self::assertStringContainsString("name: \$processedData['name'] ?? '',", $content);
        self::assertStringContainsString("email: \$processedData['email'] ?? null,", $content);
        self::assertStringContainsString("'id' => \$entity->id,", $content);
        self::assertStringContainsString("'name' => \$entity->name,", $content);
        self::assertStringContainsString("'email' => \$entity->email,", $content);
    }

    /**
     * Test generation with custom output path pattern.
     */
    public function testGenerateWithCustomOutputPath(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            hydratorInterface: 'App\\Infrastructure\\Hydrator\\TypedHydrator',
            generateHydrators: true,
            outputPattern: '{outputDir}/Custom/{className}Hydrator.php',
        );
        $generator = new HydratorGenerator($config);

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

        // Act
        $files = $generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);

        // Check custom output path based on pattern
        $filePath = 'gen/Custom/ProductHydrator.php';
        self::assertSame($filePath, $files[0]->getName());
    }

    /**
     * Test with different type mappings.
     */
    public function testGenerateWithTypeMapping(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            hydratorInterface: 'App\\Infrastructure\\Hydrator\\TypedHydrator',
            generateHydrators: true,
            typeMapping: [
                '3' => 'int',     // INT64 -> int
                '8' => 'bool',    // BOOL -> bool
                '11' => 'object', // MESSAGE -> object
            ],
        );
        $generator = new HydratorGenerator($config);

        $descriptor = new EntityDescriptor(
            name: 'Order',
            namespace: 'App\\Domain\\Order',
            tableName: 'orders',
            primaryKey: 'id',
        );

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'id',
            type: 'string', // Will be mapped to int
            nullable: false,
            columnName: 'id',
            protoType: '3', // INT64
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'isActive',
            type: 'int', // Will be mapped to bool
            nullable: false,
            columnName: 'is_active',
            protoType: '8', // BOOL
        ));

        // Act
        $files = $generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);
        $content = $files[0]->getContent();

        // The actual type mapping happens in ProtoHydratorGenerator, but
        // we can check that the basic hydrator is generated correctly
        self::assertStringContainsString('final class OrderHydrator implements TypedHydrator', $content);
        self::assertStringContainsString("id: \$processedData['id'] ?? 0,", $content);
        self::assertStringContainsString("isActive: \$processedData['isActive'] ?? false,", $content);
    }

    /**
     * Test disabling hydrator generation.
     */
    public function testGenerateHydratorDisabled(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            hydratorInterface: 'App\\Infrastructure\\Hydrator\\TypedHydrator',
            generateHydrators: false,
        );
        $generator = new HydratorGenerator($config);

        $descriptor = new EntityDescriptor(
            name: 'User',
            namespace: 'App\\Domain\\User',
            tableName: 'users',
            primaryKey: 'id',
        );

        // Act
        $files = $generator->generate($descriptor);

        // Assert
        // Should return empty array when hydrator generation is disabled
        self::assertCount(0, $files);
    }
}
