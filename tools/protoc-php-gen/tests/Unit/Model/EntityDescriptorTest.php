<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use ProtoPhpGen\Model\EntityDescriptor;
use ProtoPhpGen\Model\PropertyDescriptor;

/**
 * @covers \ProtoPhpGen\Model\EntityDescriptor
 */
final class EntityDescriptorTest extends TestCase
{
    /**
     * Тест конструктора и геттеров.
     */
    public function testConstructorAndGetters(): void
    {
        // Arrange & Act
        $descriptor = new EntityDescriptor(
            name: 'User',
            namespace: 'App\\Domain\\User',
            tableName: 'users',
            primaryKey: 'id',
        );

        // Assert
        self::assertSame('User', $descriptor->getName());
        self::assertSame('App\\Domain\\User', $descriptor->getNamespace());
        self::assertSame('users', $descriptor->getTableName());
        self::assertSame('id', $descriptor->getPrimaryKey());
        self::assertSame([], $descriptor->getProperties());
        self::assertSame('App\\Domain\\User\\User', $descriptor->getFullyQualifiedName());
        self::assertSame('entity', $descriptor->getType());
    }

    /**
     * Тест добавления свойств.
     */
    public function testAddProperty(): void
    {
        // Arrange
        $descriptor = new EntityDescriptor(
            name: 'User',
            namespace: 'App\\Domain\\User',
            tableName: 'users',
        );

        $idProperty = new PropertyDescriptor(
            name: 'id',
            type: 'int',
            nullable: false,
            columnName: 'id',
            protoType: '5', // TYPE_INT32
            repeated: false,
        );

        $nameProperty = new PropertyDescriptor(
            name: 'name',
            type: 'string',
            nullable: false,
            columnName: 'name',
            protoType: '9', // TYPE_STRING
            repeated: false,
        );

        // Act
        $descriptor->addProperty($idProperty);
        $descriptor->addProperty($nameProperty);

        // Assert
        $properties = $descriptor->getProperties();
        self::assertCount(2, $properties);
        self::assertSame($idProperty, $properties[0]);
        self::assertSame($nameProperty, $properties[1]);
    }

    /**
     * Тест установки типа генератора.
     */
    public function testSetType(): void
    {
        // Arrange
        $descriptor = new EntityDescriptor(
            name: 'User',
            namespace: 'App\\Domain\\User',
            tableName: 'users',
        );

        // Act
        $descriptor->setType('hydrator');

        // Assert
        self::assertSame('hydrator', $descriptor->getType());
    }
}
