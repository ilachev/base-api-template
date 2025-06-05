<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;
use ProtoPhpGen\Generator\CodeGeneratorFactory;
use ProtoPhpGen\Generator\MapperGenerator;
use ProtoPhpGen\Generator\ProtoMapperGenerator;
use ProtoPhpGen\Generator\ProtoDomainMapperGenerator;

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
     * Тест создания генератора стандартных мапперов.
     */
    public function testCreateMapperGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('mapper');

        // Assert
        self::assertInstanceOf(MapperGenerator::class, $generator);
    }

    /**
     * Тест создания генератора прото-мапперов.
     */
    public function testCreateProtoMapperGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('proto_mapper');

        // Assert
        self::assertInstanceOf(ProtoMapperGenerator::class, $generator);
    }

    /**
     * Тест создания генератора прото-домен мапперов.
     */
    public function testCreateProtoDomainMapperGenerator(): void
    {
        // Act
        $generator = $this->factory->createGenerator('proto_domain_mapper');

        // Assert
        self::assertInstanceOf(ProtoDomainMapperGenerator::class, $generator);
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
