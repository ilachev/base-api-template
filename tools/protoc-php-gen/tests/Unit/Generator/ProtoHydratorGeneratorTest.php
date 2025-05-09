<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\ProtoHydratorGenerator;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;
use RuntimeException;

/**
 * @covers \ProtoPhpGen\Generator\ProtoHydratorGenerator
 */
final class ProtoHydratorGeneratorTest extends TestCase
{
    private GeneratorConfig $config;
    private ProtoHydratorGenerator $generator;

    protected function setUp(): void
    {
        $this->config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            hydratorInterface: 'App\\Infrastructure\\Hydrator\\TypedHydrator',
            generateProtoHydrators: true,
        );
        $this->generator = new ProtoHydratorGenerator($this->config);
    }

    /**
     * Test generation of proto hydrator with basic properties.
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
            name: 'createdAt',
            type: '\\DateTime',
            nullable: true,
            columnName: 'created_at',
        ));

        // Act
        $files = $this->generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);

        $filePath = 'gen/Infrastructure/Hydrator/Proto/UserProtoHydrator.php';
        self::assertSame($filePath, $files[0]->getName());

        $content = $files[0]->getContent();

        // Check for key elements in the generated code
        self::assertStringContainsString('declare(strict_types=1);', $content);
        self::assertStringContainsString('namespace App\\Gen\\Infrastructure\\Hydrator\\Proto;', $content);
        self::assertStringContainsString('use App\\Gen\\Domain\\User;', $content);
        self::assertStringContainsString('use App\\Gen\\Api\\V1\\UserProto;', $content);
        self::assertStringContainsString('use RuntimeException;', $content);
        self::assertStringContainsString('final class UserProtoHydrator', $content);

        // Check the hydrate method with improved error handling
        self::assertStringContainsString('public function hydrate', $content);
        self::assertStringContainsString('try {', $content);
        self::assertStringContainsString('$entity = new User();', $content);

        // Check for type conversion
        self::assertStringContainsString('(int)', $content);

        // Check for error handling
        self::assertStringContainsString('catch (\\Throwable $e)', $content);
        self::assertStringContainsString('throw new \\RuntimeException', $content);

        // Check the getNestedConverter method
        self::assertStringContainsString('private function getNestedConverter', $content);
    }

    /**
     * Test hydrator generation with complex nested types and type mapping.
     */
    public function testGenerateWithComplexTypes(): void
    {
        // Arrange
        // Create a config with type mapping
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            hydratorInterface: 'App\\Infrastructure\\Hydrator\\TypedHydrator',
            generateProtoHydrators: true,
            domainNamespace: 'App\\Domain',
            protoNamespace: 'App\\Api\\V1',
            typeMapping: [
                '1' => 'int',    // TYPE_DOUBLE -> int
                '2' => 'float',  // TYPE_FLOAT -> float
                '8' => 'bool',   // TYPE_BOOL -> bool
            ],
            outputPattern: '{outputDir}/Hydrator/Proto/{className}.php',
        );

        $generator = new ProtoHydratorGenerator($config);

        $descriptor = new EntityDescriptor(
            name: 'Order',
            namespace: 'App\\Domain\\Order',
            tableName: 'orders',
            primaryKey: 'id',
        );

        // Add simple properties
        $descriptor->addProperty(new PropertyDescriptor(
            name: 'id',
            type: 'int',
            nullable: false,
            columnName: 'id',
            protoType: '3', // TYPE_INT64
        ));

        // Add boolean property
        $descriptor->addProperty(new PropertyDescriptor(
            name: 'isCompleted',
            type: 'bool',
            nullable: false,
            columnName: 'is_completed',
            protoType: '8', // TYPE_BOOL
        ));

        // Add array property (repeated field)
        $descriptor->addProperty(new PropertyDescriptor(
            name: 'items',
            type: 'array',
            nullable: false,
            columnName: 'items',
            repeated: true,
        ));

        // Add a nested object
        $descriptor->addProperty(new PropertyDescriptor(
            name: 'customer',
            type: 'App\\Domain\\Customer',
            nullable: true,
            columnName: 'customer',
            protoType: '11', // TYPE_MESSAGE
        ));

        // Act
        $files = $generator->generate($descriptor);

        // Assert
        self::assertCount(1, $files);

        // Check that the file path follows our custom pattern
        $filePath = 'gen/Hydrator/Proto/OrderProtoHydrator.php';
        self::assertSame($filePath, $files[0]->getName());

        $content = $files[0]->getContent();

        // Check uses correct namespaces from configuration
        self::assertStringContainsString('use App\\Domain\\Order;', $content);
        self::assertStringContainsString('use App\\Api\\V1\\OrderProto;', $content);

        // Check handling of array property
        self::assertStringContainsString('$arrayValue = $proto->getItems();', $content);
        self::assertStringContainsString('$entity->setItems([]);', $content);

        // Check handling of nested object
        self::assertStringContainsString('$nestedObject = $entity->getCustomer();', $content);
        self::assertStringContainsString('$converter = $this->getNestedConverter($nestedObject::class);', $content);

        // Check boolean conversion
        self::assertStringContainsString('(bool)$value', $content);
    }

    /**
     * Test custom naming conventions with camelToSnake improvements.
     */
    public function testCustomNamingConventions(): void
    {
        // Arrange
        $descriptor = new EntityDescriptor(
            name: 'APIClient',
            namespace: 'App\\Domain\\API',
            tableName: 'api_clients',
            primaryKey: 'id',
        );

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'userID',
            type: 'string',
            nullable: false,
            columnName: 'user_id',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'clientAPIKey',
            type: 'string',
            nullable: false,
            columnName: 'client_api_key',
        ));

        $descriptor->addProperty(new PropertyDescriptor(
            name: 'HTTPTimeout',
            type: 'int',
            nullable: true,
            columnName: 'http_timeout',
        ));

        // Act
        $files = $this->generator->generate($descriptor);

        // Assert
        $content = $files[0]->getContent();

        // Test camelToSnake handling for special cases
        self::assertStringContainsString('$proto->setUser_id($entity->getUserID());', $content);
        self::assertStringContainsString('$proto->setClient_api_key($entity->getClientAPIKey());', $content);
        self::assertStringContainsString('$proto->setHttp_timeout($entity->getHTTPTimeout());', $content);
    }

    /**
     * Test that nothing is generated when proto hydrators are disabled.
     */
    public function testGenerateDisabled(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            hydratorInterface: 'App\\Infrastructure\\Hydrator\\TypedHydrator',
            generateProtoHydrators: false,
        );
        $generator = new ProtoHydratorGenerator($config);

        $descriptor = new EntityDescriptor(
            name: 'User',
            namespace: 'App\\Domain\\User',
            tableName: 'users',
            primaryKey: 'id',
        );

        // Act
        $files = $generator->generate($descriptor);

        // Assert
        self::assertCount(0, $files);
    }
}
