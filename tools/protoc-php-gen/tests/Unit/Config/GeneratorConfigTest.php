<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Config\GeneratorConfig;

/**
 * @covers \ProtoPhpGen\Config\GeneratorConfig
 */
final class GeneratorConfigTest extends TestCase
{
    /**
     * Тест конструктора с параметрами по умолчанию.
     */
    public function testConstructorWithDefaults(): void
    {
        // Act
        $config = new GeneratorConfig();

        // Assert
        self::assertSame('App\Gen', $config->getNamespace());
        self::assertSame('gen', $config->getOutputDir());
        self::assertSame('App\Domain\Entity', $config->getEntityInterface());
        self::assertTrue($config->shouldGenerateRepositories());
        self::assertTrue($config->shouldGenerateHydrators());
    }

    /**
     * Тест конструктора с пользовательскими параметрами.
     */
    public function testConstructorWithCustomValues(): void
    {
        // Act
        $config = new GeneratorConfig(
            namespace: 'Custom\Namespace',
            outputDir: 'custom-output',
            entityInterface: 'Custom\Entity',
            generateRepositories: false,
            generateHydrators: false,
        );

        // Assert
        self::assertSame('Custom\Namespace', $config->getNamespace());
        self::assertSame('custom-output', $config->getOutputDir());
        self::assertSame('Custom\Entity', $config->getEntityInterface());
        self::assertFalse($config->shouldGenerateRepositories());
        self::assertFalse($config->shouldGenerateHydrators());
    }

    /**
     * Тест сеттеров и геттеров.
     */
    public function testSettersAndGetters(): void
    {
        // Arrange
        $config = new GeneratorConfig();

        // Act & Assert - Namespace
        $result = $config->setNamespace('New\Namespace');
        self::assertSame($config, $result);
        self::assertSame('New\Namespace', $config->getNamespace());

        // Act & Assert - OutputDir
        $result = $config->setOutputDir('new-output');
        self::assertSame($config, $result);
        self::assertSame('new-output', $config->getOutputDir());

        // Act & Assert - EntityInterface
        $result = $config->setEntityInterface('New\Entity');
        self::assertSame($config, $result);
        self::assertSame('New\Entity', $config->getEntityInterface());

        // Act & Assert - GenerateRepositories
        $result = $config->setGenerateRepositories(false);
        self::assertSame($config, $result);
        self::assertFalse($config->shouldGenerateRepositories());

        // Act & Assert - GenerateHydrators
        $result = $config->setGenerateHydrators(false);
        self::assertSame($config, $result);
        self::assertFalse($config->shouldGenerateHydrators());
    }
}
