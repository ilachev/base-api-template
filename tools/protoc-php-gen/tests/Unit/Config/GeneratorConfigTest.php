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
     * Test constructor with default parameters.
     */
    public function testConstructorWithDefaults(): void
    {
        // Act
        $config = new GeneratorConfig();

        // Assert
        self::assertSame('App\Gen', $config->getNamespace());
        self::assertSame('gen', $config->getOutputDir());
        self::assertSame('App\Infrastructure\Hydrator\TypedHydrator', $config->getHydratorInterface());
        self::assertTrue($config->shouldGenerateHydrators());
        self::assertFalse($config->shouldGenerateProtoHydrators());
        self::assertFalse($config->isStandaloneMode());
        self::assertNull($config->getDomainNamespace());
        self::assertNull($config->getProtoNamespace());
        self::assertEmpty($config->getTypeMapping());
    }

    /**
     * Test constructor with custom parameters.
     */
    public function testConstructorWithCustomValues(): void
    {
        // Act
        $config = new GeneratorConfig(
            namespace: 'Custom\Namespace',
            outputDir: 'custom-output',
            hydratorInterface: 'Custom\Hydrator',
            generateHydrators: false,
            generateProtoHydrators: true,
            standaloneMode: true,
            domainNamespace: 'Domain\Namespace',
            protoNamespace: 'Proto\Namespace',
            typeMapping: ['1' => 'int', '2' => 'float'],
            outputPattern: '{outputDir}/{className}.php',
        );

        // Assert
        self::assertSame('Custom\Namespace', $config->getNamespace());
        self::assertSame('custom-output', $config->getOutputDir());
        self::assertSame('Custom\Hydrator', $config->getHydratorInterface());
        self::assertFalse($config->shouldGenerateHydrators());
        self::assertTrue($config->shouldGenerateProtoHydrators());
        self::assertTrue($config->isStandaloneMode());
        self::assertSame('Domain\Namespace', $config->getDomainNamespace());
        self::assertSame('Proto\Namespace', $config->getProtoNamespace());
        self::assertSame(['1' => 'int', '2' => 'float'], $config->getTypeMapping());
        self::assertSame('{outputDir}/{className}.php', $config->getOutputPattern());
    }

    /**
     * Test setters and getters.
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

        // Act & Assert - HydratorInterface
        $result = $config->setHydratorInterface('New\Hydrator');
        self::assertSame($config, $result);
        self::assertSame('New\Hydrator', $config->getHydratorInterface());

        // Act & Assert - GenerateHydrators
        $result = $config->setGenerateHydrators(false);
        self::assertSame($config, $result);
        self::assertFalse($config->shouldGenerateHydrators());

        // Act & Assert - GenerateProtoHydrators
        $result = $config->setGenerateProtoHydrators(true);
        self::assertSame($config, $result);
        self::assertTrue($config->shouldGenerateProtoHydrators());

        // Act & Assert - StandaloneMode
        $result = $config->setStandaloneMode(true);
        self::assertSame($config, $result);
        self::assertTrue($config->isStandaloneMode());

        // Act & Assert - DomainNamespace
        $result = $config->setDomainNamespace('Domain\Ns');
        self::assertSame($config, $result);
        self::assertSame('Domain\Ns', $config->getDomainNamespace());

        // Act & Assert - ProtoNamespace
        $result = $config->setProtoNamespace('Proto\Ns');
        self::assertSame($config, $result);
        self::assertSame('Proto\Ns', $config->getProtoNamespace());

        // Act & Assert - TypeMapping
        $result = $config->setTypeMapping(['8' => 'bool']);
        self::assertSame($config, $result);
        self::assertSame(['8' => 'bool'], $config->getTypeMapping());

        // Act & Assert - OutputPattern
        $result = $config->setOutputPattern('pattern/{className}');
        self::assertSame($config, $result);
        self::assertSame('pattern/{className}', $config->getOutputPattern());
    }

    /**
     * Test mapType method for type mapping.
     */
    public function testMapType(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            typeMapping: [
                '1' => 'integer',
                '2' => 'double',
                '8' => 'boolean',
            ],
        );

        // Act & Assert
        self::assertSame('integer', $config->mapType('1', 'int'));
        self::assertSame('double', $config->mapType('2', 'float'));
        self::assertSame('boolean', $config->mapType('8', 'bool'));

        // Act & Assert - Default value for unmapped type
        self::assertSame('string', $config->mapType('9', 'string'));
    }

    /**
     * Test getOutputPath method for output path templating.
     */
    public function testGetOutputPath(): void
    {
        // Arrange
        $config = new GeneratorConfig(
            outputDir: 'gen',
            outputPattern: '{outputDir}/Custom/{className}_{type}.php',
        );

        // Act - With pattern
        $path1 = $config->getOutputPath('User', 'hydrator', 'fallback.php');

        // Assert
        self::assertSame('gen/Custom/User_hydrator.php', $path1);

        // Act - Without pattern
        $config->setOutputPattern(null);
        $path2 = $config->getOutputPath('User', 'hydrator', 'fallback.php');

        // Assert
        self::assertSame('fallback.php', $path2);
    }
}
