<?php

declare(strict_types=1);

namespace Tests\Unit\Protoc;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Protoc\PluginRequest;

/**
 * @covers \ProtoPhpGen\Protoc\PluginRequest
 */
final class PluginRequestTest extends TestCase
{
    /**
     * Тест получения параметров.
     */
    public function testGetParameter(): void
    {
        // Arrange
        $request = new PluginRequest();
        $request->setParameter('key1=value1&key2=value2&key3=value3');

        // Act & Assert
        self::assertTrue($request->hasParameter('key1'));
        self::assertFalse($request->hasParameter('nonexistent'));
        self::assertSame('value1', $request->getParameter('key1'));
        self::assertSame('value2', $request->getParameter('key2'));
        self::assertSame('default', $request->getParameter('nonexistent', 'default'));
    }

    /**
     * Тест получения всех параметров.
     */
    public function testGetParameters(): void
    {
        // Arrange
        $request = new PluginRequest();
        $request->setParameter('key1=value1&key2=value2&key3=value3');

        // Act
        $parameters = $request->getParameters();

        // Assert
        self::assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ], $parameters);
    }

    /**
     * Тест добавления файлов для генерации.
     */
    public function testAddFileToGenerate(): void
    {
        // Arrange
        $request = new PluginRequest();

        // Act
        $request->addFileToGenerate('file1.proto');
        $request->addFileToGenerate('file2.proto');

        // Assert
        $files = $request->getFilesToGenerate();
        self::assertCount(2, $files);
        self::assertSame('file1.proto', $files[0]);
        self::assertSame('file2.proto', $files[1]);
    }

    /**
     * Тест добавления proto файлов.
     */
    public function testAddProtoFile(): void
    {
        // Arrange
        $request = new PluginRequest();
        $protoFile1 = ['name' => 'file1.proto', 'package' => 'test.package'];
        $protoFile2 = ['name' => 'file2.proto', 'package' => 'test.package'];

        // Act
        $request->addProtoFile('file1.proto', $protoFile1);
        $request->addProtoFile('file2.proto', $protoFile2);

        // Assert
        $protoFiles = $request->getProtoFiles();
        self::assertCount(2, $protoFiles);
        self::assertSame($protoFile1, $protoFiles['file1.proto']);
        self::assertSame($protoFile2, $protoFiles['file2.proto']);
        self::assertSame($protoFile1, $request->getProtoFile('file1.proto'));
        self::assertNull($request->getProtoFile('nonexistent.proto'));
    }
}
