<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Hydrator;

use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\HydratorException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\Hydrator\Fixtures\{
    EmptyEntity,
    EntityWithNullableProperty,
    EntityWithPrivateProperty,
    EntityWithProtectedProperty,
    TestEntity
};

final class HydratorTest extends TestCase
{
    private Hydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new Hydrator();
    }

    public function testHydrateCreatesObject(): void
    {
        $data = ['id' => 1, 'name' => 'Test', 'initialized' => true];
        $object = $this->hydrator->hydrate(TestEntity::class, $data);

        self::assertInstanceOf(TestEntity::class, $object);
        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
        self::assertTrue($object->initialized);
    }

    public function testHydrateWithDefaultValue(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $object = $this->hydrator->hydrate(TestEntity::class, $data);

        self::assertInstanceOf(TestEntity::class, $object);
        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
        self::assertFalse($object->initialized);
    }

    public function testHydrateWithExtraData(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'initialized' => true,
            'extraField' => 'extra',
        ];
        $object = $this->hydrator->hydrate(TestEntity::class, $data);

        self::assertInstanceOf(TestEntity::class, $object);
        // Проверяем только ожидаемые свойства
        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
        self::assertTrue($object->initialized);
    }

    public function testHydrateWithNullableProperty(): void
    {
        $object = $this->hydrator->hydrate(EntityWithNullableProperty::class, ['nullableField' => null]);

        self::assertInstanceOf(EntityWithNullableProperty::class, $object);
        self::assertNull($object->nullableField);
    }

    public function testHydrateEmptyEntity(): void
    {
        $object = $this->hydrator->hydrate(EmptyEntity::class, []);

        self::assertInstanceOf(EmptyEntity::class, $object);
    }

    public function testHydrateWithPrivatePropertyThrowsException(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage(
            'Class Tests\Unit\Infrastructure\Hydrator\Fixtures\EntityWithPrivateProperty '
            . 'contains non-public properties: privateField. Only public properties are allowed.',
        );

        $this->hydrator->hydrate(EntityWithPrivateProperty::class, ['privateField' => 'test']);
    }

    public function testHydrateWithProtectedPropertyThrowsException(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage(
            'Class Tests\Unit\Infrastructure\Hydrator\Fixtures\EntityWithProtectedProperty '
            . 'contains non-public properties: protectedField. Only public properties are allowed.',
        );

        $this->hydrator->hydrate(EntityWithProtectedProperty::class, ['protectedField' => 'test']);
    }

    public function testHydrateWithNonExistentClass(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Failed to create reflection for class NonExistentClass');

        /** @var class-string<object> $nonExistentClass */
        $nonExistentClass = 'NonExistentClass';
        $this->hydrator->hydrate($nonExistentClass, []);
    }

    public function testHydrateMissingRequiredParameter(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Missing required constructor parameter: id');

        $this->hydrator->hydrate(TestEntity::class, ['name' => 'Test']);
    }

    public function testHydrateWithInvalidPropertyType(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage(
            'Tests\Unit\Infrastructure\Hydrator\Fixtures\TestEntity::__construct(): '
            . 'Argument #1 ($id) must be of type int, string given',
        );

        $this->hydrator->hydrate(TestEntity::class, [
            'id' => 'not an integer',
            'name' => 'Test',
        ]);
    }

    public function testExtract(): void
    {
        $entity = new TestEntity(1, 'Test');
        $data = $this->hydrator->extract($entity);

        self::assertSame([
            'id' => 1,
            'name' => 'Test',
            'initialized' => false,
        ], $data);
    }

    public function testExtractEmptyObject(): void
    {
        $object = new EmptyEntity();
        $data = $this->hydrator->extract($object);

        self::assertSame([], $data);
    }

    public function testExtractWithNonObject(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Failed to extract data: argument must be an object');

        /** @psalm-suppress InvalidArgument */
        $this->hydrator->extract('not an object');
    }

    public function testExtractWithPrivatePropertyThrowsException(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage(
            'Class Tests\Unit\Infrastructure\Hydrator\Fixtures\EntityWithPrivateProperty '
            . 'contains non-public properties: privateField. Only public properties are allowed.',
        );

        $object = new EntityWithPrivateProperty();
        $this->hydrator->extract($object);
    }

    public function testExtractWithProtectedPropertyThrowsException(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage(
            'Class Tests\Unit\Infrastructure\Hydrator\Fixtures\EntityWithProtectedProperty '
            . 'contains non-public properties: protectedField. Only public properties are allowed.',
        );

        $object = new EntityWithProtectedProperty();
        $this->hydrator->extract($object);
    }

    public function testExtractWithNullableProperty(): void
    {
        $object = new EntityWithNullableProperty(null);
        $data = $this->hydrator->extract($object);

        self::assertArrayHasKey('nullableField', $data);
        self::assertNull($data['nullableField']);
    }
}
