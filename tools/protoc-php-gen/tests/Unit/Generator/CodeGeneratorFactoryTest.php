<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\CodeGeneratorFactory;
use ProtoPhpGen\Generator\EntityGenerator;
use ProtoPhpGen\Generator\HydratorGenerator;
use ProtoPhpGen\Generator\RepositoryGenerator;

/**
 * @covers \ProtoPhpGen\Generator\CodeGeneratorFactory
 */
final class CodeGeneratorFactoryTest extends TestCase
{
    private GeneratorConfig $config;
    private CodeGeneratorFactory $factory;

    protected function setUp(): void
    {
        $this->config = new GeneratorConfig();
        $this->factory = new CodeGeneratorFactory($this->config);
    }

    /**
     * Тест создания генератора сущностей.
     */
    public function testCreateEntityGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('entity');

        // Assert
        self::assertInstanceOf(EntityGenerator::class, $generator);
    }

    /**
     * Тест создания генератора гидраторов.
     */
    public function testCreateHydratorGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('hydrator');

        // Assert
        self::assertInstanceOf(HydratorGenerator::class, $generator);
    }

    /**
     * Тест создания генератора репозиториев.
     */
    public function testCreateRepositoryGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('repository');

        // Assert
        self::assertInstanceOf(RepositoryGenerator::class, $generator);
    }

    /**
     * Тест создания генератора с неподдерживаемым типом.
     */
    public function testCreateGeneratorWithUnsupportedType(): void
    {
        // Arrange
        $unsupportedType = 'unsupported';

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported generator type: {$unsupportedType}");
        
        $this->factory->createGenerator($unsupportedType);
    }
}
