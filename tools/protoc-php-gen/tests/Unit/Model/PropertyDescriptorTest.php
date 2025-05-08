<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Model\PropertyDescriptor;

/**
 * @covers \ProtoPhpGen\Model\PropertyDescriptor
 */
final class PropertyDescriptorTest extends TestCase
{
    /**
     * Тест конструктора с параметрами по умолчанию.
     */
    public function testConstructorWithDefaults(): void
    {
        // Arrange & Act
        $property = new PropertyDescriptor(
            name: 'userId',
            type: 'int',
        );

        // Assert
        self::assertSame('userId', $property->name);
        self::assertSame('int', $property->type);
        self::assertFalse($property->nullable);
        self::assertNull($property->columnName);
        self::assertNull($property->protoType);
        self::assertFalse($property->repeated);
        self::assertSame('user_id', $property->getColumnName());
    }

    /**
     * Тест конструктора с заданными параметрами.
     */
    public function testConstructorWithCustomValues(): void
    {
        // Arrange & Act
        $property = new PropertyDescriptor(
            name: 'userId',
            type: 'int',
            nullable: true,
            columnName: 'custom_column',
            protoType: '5', // TYPE_INT32
            repeated: true,
        );

        // Assert
        self::assertSame('userId', $property->name);
        self::assertSame('int', $property->type);
        self::assertTrue($property->nullable);
        self::assertSame('custom_column', $property->columnName);
        self::assertSame('5', $property->protoType);
        self::assertTrue($property->repeated);
        self::assertSame('custom_column', $property->getColumnName());
    }

    /**
     * Тест преобразования имени в формат столбца базы данных.
     */
    public function testGetColumnNameWithoutCustomColumn(): void
    {
        // Arrange & Act & Assert
        $property = new PropertyDescriptor(name: 'userId', type: 'int');
        self::assertSame('user_id', $property->getColumnName());

        $property = new PropertyDescriptor(name: 'firstName', type: 'string');
        self::assertSame('first_name', $property->getColumnName());

        $property = new PropertyDescriptor(name: 'isActive', type: 'bool');
        self::assertSame('is_active', $property->getColumnName());

        // Проверка для простых имен без camelCase
        $property = new PropertyDescriptor(name: 'name', type: 'string');
        self::assertSame('name', $property->getColumnName());
    }
}
