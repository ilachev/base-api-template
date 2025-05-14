<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\CodeGeneratorFactory;
use ProtoPhpGen\Generator\HydratorGenerator;
use ProtoPhpGen\Generator\ProtoHydratorGenerator;
use ProtoPhpGen\Generator\StandaloneHydratorGenerator;

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
     * Тест создания генератора стандартных гидраторов.
     */
    public function testCreateHydratorGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('hydrator');

        // Assert
        self::assertInstanceOf(HydratorGenerator::class, $generator);
    }

    /**
     * Тест создания генератора прото-гидраторов.
     */
    public function testCreateProtoHydratorGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('proto_hydrator');

        // Assert
        self::assertInstanceOf(ProtoHydratorGenerator::class, $generator);
    }

    /**
     * Тест создания автономного генератора гидраторов.
     */
    public function testCreateStandaloneHydratorGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('standalone_hydrator');

        // Assert
        self::assertInstanceOf(StandaloneHydratorGenerator::class, $generator);
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
