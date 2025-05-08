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
            entityInterface: 'App\\Domain\\Entity',
            generateHydrators: true,
        );
        $this->generator = new HydratorGenerator($this->config);
    }

    /**
     * Тест генерации гидратора для сущности.
     */
    public function testGenerateHydrator(): void
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

        // Проверяем основные элементы сгенерированного кода
        self::assertStringContainsString('declare(strict_types=1);', $content);
        self::assertStringContainsString('namespace App\\Gen\\Infrastructure\\Hydrator;', $content);
        self::assertStringContainsString('use App\\Gen\\Domain\\User;', $content);
        self::assertStringContainsString('use App\\Infrastructure\\Hydrator\\TypedHydrator;', $content);
        self::assertStringContainsString('final class UserHydrator implements \\TypedHydrator', $content);
        self::assertStringContainsString('public function getEntityClass(): string', $content);
        self::assertStringContainsString('return User::class;', $content);
        self::assertStringContainsString('public function hydrate(array $data): \\User', $content);
        self::assertStringContainsString('public function extract(\\User $entity): array', $content);
        
        // Проверяем методы гидратации и экстракции
        self::assertStringContainsString("id: \$data['id'] ?? 0,", $content);
        self::assertStringContainsString("name: \$data['name'] ?? '',", $content);
        self::assertStringContainsString("email: \$data['email'] ?? null,", $content);
        self::assertStringContainsString("'id' => \$entity->id,", $content);
        self::assertStringContainsString("'name' => \$entity->name,", $content);
        self::assertStringContainsString("'email' => \$entity->email,", $content);
    }

    /**
     * Тест отключения генерации гидраторов.
     */
    public function testGenerateHydratorDisabled(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            namespace: 'App\\Gen',
            outputDir: 'gen',
            entityInterface: 'App\\Domain\\Entity',
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
        // Если генерация гидраторов отключена, то должен быть пустой массив
        self::assertCount(0, $files);
    }
}
