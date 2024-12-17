<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DI;

use App\Infrastructure\DI\Container;
use App\Infrastructure\DI\ContainerException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Infrastructure\DI\Fixtures\{
    SimpleClass,
    Dependency,
    ServiceWithDependency,
    NestedDependency,
    ParentDependency,
    ServiceWithNestedDependency,
    ServiceWithDefaultValue,
    ServiceWithBuiltinType,
    ServiceWithUnionType,
    ServiceWithNoTypeHint,
    Circular1,
    TestInterface,
    Implementation1,
    Implementation2
};
use Psr\Container\ContainerInterface;

final class ContainerTest extends TestCase
{
    /**
     * @var Container<object>
     */
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testSetAndGetService(): void
    {
        // Arrange
        $service = new \stdClass();
        $this->container->set(\stdClass::class, fn() => $service);

        // Act
        $result = $this->container->get(\stdClass::class);

        // Assert
        self::assertSame($service, $result);
    }

    public function testServiceIsSingleton(): void
    {
        // Arrange
        $this->container->set(\stdClass::class, fn() => new \stdClass());

        // Act
        $first = $this->container->get(\stdClass::class);
        $second = $this->container->get(\stdClass::class);

        // Assert
        self::assertSame($first, $second);
    }

    public function testBindInterface(): void
    {
        // Arrange
        $implementation = new Implementation1();
        $this->container->set(Implementation1::class, fn() => $implementation);
        $this->container->bind(TestInterface::class, Implementation1::class);

        // Act
        $result = $this->container->get(TestInterface::class);

        // Assert
        self::assertSame($implementation, $result);
    }

    public function testHasReturnsTrueForRegisteredService(): void
    {
        // Arrange
        $this->container->set(\stdClass::class, fn() => new \stdClass());

        // Act & Assert
        self::assertTrue($this->container->has(\stdClass::class));
    }

    public function testHasReturnsFalseForUnregisteredService(): void
    {
        // Act & Assert
        self::assertFalse($this->container->has(\stdClass::class));
    }

    public function testAutoWiringSimpleClass(): void
    {
        // Act
        $result = $this->container->get(SimpleClass::class);

        // Assert
        self::assertInstanceOf(SimpleClass::class, $result);
    }

    public function testAutoWiringWithDependencies(): void
    {
        // Act
        $result = $this->container->get(ServiceWithDependency::class);

        // Assert
        self::assertInstanceOf(ServiceWithDependency::class, $result);
        self::assertInstanceOf(Dependency::class, $result->dependency);
    }

    public function testAutoWiringWithNestedDependencies(): void
    {
        // Act
        $result = $this->container->get(ServiceWithNestedDependency::class);

        // Assert
        self::assertInstanceOf(ServiceWithNestedDependency::class, $result);
        self::assertInstanceOf(ParentDependency::class, $result->parent);
        self::assertInstanceOf(NestedDependency::class, $result->parent->nested);
    }

    public function testAutoWiringWithDefaultValue(): void
    {
        // Act
        $result = $this->container->get(ServiceWithDefaultValue::class);

        // Assert
        self::assertInstanceOf(ServiceWithDefaultValue::class, $result);
        self::assertEquals('default', $result->param);
    }

    public function testGetNonExistentClassThrowsException(): void
    {
        // Assert
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Class NonExistentClass does not exist');

        // Act
        /** @var class-string<object> $nonExistentClass */
        $nonExistentClass = 'NonExistentClass';
        $this->container->get($nonExistentClass);
    }
    public function testGetWithUnresolvableBuiltinTypeThrowsException(): void
    {
        // Assert
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve built-in type for parameter value');

        // Act
        $this->container->get(ServiceWithBuiltinType::class);
    }

    public function testGetWithUnionTypeThrowsException(): void
    {
        // Assert
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve union or intersection type for parameter param');

        // Act
        $this->container->get(ServiceWithUnionType::class);
    }

    public function testGetWithNoTypeHintThrowsException(): void
    {
        // Assert
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve parameter param: no type hint');

        // Act
        $this->container->get(ServiceWithNoTypeHint::class);
    }

    public function testCircularDependencyThrowsException(): void
    {
        // Assert
        $this->expectException(ContainerException::class);

        // Act
        $this->container->get(Circular1::class);
    }

    public function testContainerPassedToFactory(): void
    {
        // Arrange
        $containerPassed = null;
        $this->container->set(\stdClass::class, function (ContainerInterface $container) use (&$containerPassed) {
            $containerPassed = $container;
            return new \stdClass();
        });

        // Act
        $this->container->get(\stdClass::class);

        // Assert
        self::assertSame($this->container, $containerPassed);
    }

    public function testMultipleBindings(): void
    {
        // Arrange
        $this->container->bind(TestInterface::class, Implementation1::class);
        $first = $this->container->get(TestInterface::class);

        // Act
        $this->container->bind(TestInterface::class, Implementation2::class);
        $second = $this->container->get(TestInterface::class);

        // Assert
        self::assertInstanceOf(Implementation1::class, $first);
        self::assertInstanceOf(Implementation2::class, $second);
    }
}
