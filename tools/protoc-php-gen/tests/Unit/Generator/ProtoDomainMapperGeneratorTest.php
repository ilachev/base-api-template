<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Generator\ProtoDomainMapperGenerator;
use ProtoPhpGen\Model\ClassMapping;
use ProtoPhpGen\Model\FieldMapping;

/**
 * @covers \ProtoPhpGen\Generator\ProtoDomainMapperGenerator
 */
final class ProtoDomainMapperGeneratorTest extends TestCase
{
    private ProtoDomainMapperGenerator $generator;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/mapper_test';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        $this->generator = new ProtoDomainMapperGenerator();
    }

    /**
     * Test generation of proto domain mapper.
     */
    public function testGenerate(): void
    {
        // Arrange
        $mapping = new ClassMapping(
            'App\\Domain\\User\\User',
            'App\\Api\\V1\\User',
        );

        $mapping->addFieldMapping(new FieldMapping(
            'id',
            'id',
            'default',
        ));

        $mapping->addFieldMapping(new FieldMapping(
            'passwordHash',
            'password_hash',
            'default',
        ));

        $mapping->addFieldMapping(new FieldMapping(
            'createdAt',
            'created_at',
            'datetime',
        ));

        // Act
        $outputPath = $this->generator->generateFromMapping($mapping, $this->outputDir);

        // Assert
        self::assertFileExists($outputPath);
        
        $content = file_get_contents($outputPath);
        
        // Check for mapper-specific elements
        self::assertStringContainsString('class UserProtoMapper', $content);
        self::assertStringContainsString('function hydrate', $content);
        self::assertStringContainsString('function extract', $content);
        
        // Check hydration and extraction methods
        self::assertStringContainsString('new UserUser(', $content);
        self::assertStringContainsString('$proto->setPasswordHash', $content);
        self::assertStringContainsString('$proto->setCreatedAt', $content);
        
        self::assertStringContainsString('$proto->setId($entity->id)', $content);
        self::assertStringContainsString('$proto->setPasswordHash($entity->passwordHash)', $content);
    }

    /**
     * Test generation of standalone hydrator with complex types.
     */
    public function testGenerateWithComplexTypes(): void
    {
        // Arrange
        $mapping = new ClassMapping(
            'App\\Domain\\Session\\Session',
            'App\\Api\\V1\\Session',
        );

        $mapping->addFieldMapping(new FieldMapping(
            'id',
            'id',
            'default',
        ));

        $mapping->addFieldMapping(new FieldMapping(
            'payload',
            'payload',
            'json',
        ));

        $mapping->addFieldMapping(new FieldMapping(
            'updatedAt',
            'updated_at',
            'datetime',
        ));

        // Act
        $outputPath = $this->generator->generateFromMapping($mapping, $this->outputDir);

        // Assert
        self::assertFileExists($outputPath);
        
        $content = file_get_contents($outputPath);
        
        // Check handling of complex types
        self::assertStringContainsString('json_encode', $content);
        self::assertStringContainsString('getTimestamp', $content);
    }

    /**
     * Test generate method for Generator interface compatibility
     */
    public function testGenerateInterfaceMethod(): void
    {
        // Create a minimal entity descriptor
        $descriptor = new \ProtoPhpGen\Model\EntityDescriptor(
            name: 'TestEntity',
            namespace: 'App\\Domain\\Test',
            tableName: 'test_entities',
            primaryKey: 'id'
        );
        
        // Calling the method that implements the Generator interface
        $files = $this->generator->generate($descriptor);
        
        // Assert
        self::assertIsArray($files);
        self::assertEmpty($files);
    }

    /**
     * Test using custom namespace.
     */
    public function testCustomNamespace(): void
    {
        // Arrange
        $mapping = new ClassMapping(
            'App\\Domain\\Session\\Session',
            'App\\Api\\V1\\Session',
        );

        $mapping->addFieldMapping(new FieldMapping(
            'id',
            'id',
            'default',
        ));

        $customNamespace = 'App\\Custom\\Hydrators';
        $customOutputDir = $this->outputDir . '/custom';
        
        if (!is_dir($customOutputDir)) {
            mkdir($customOutputDir, 0755, true);
        }

        // Act
        $outputPath = $this->generator->generateFromMapping($mapping, $customOutputDir, $customNamespace);

        // Assert
        self::assertFileExists($outputPath);
        
        $content = file_get_contents($outputPath);
        
        // Check that namespace is correctly set
        self::assertStringContainsString("namespace {$customNamespace};", $content);
    }
    
    protected function tearDown(): void
    {
        // Clean up temporary directory
        $this->removeDirectory($this->outputDir);
    }
    
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}