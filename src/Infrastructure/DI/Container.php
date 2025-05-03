<?php

declare(strict_types=1);

namespace App\Infrastructure\DI;

/**
 * @template-covariant T of object
 */
interface Container
{
    /**
     * @template U of object
     * @param class-string<U> $id
     * @return U
     * @throws ContainerException
     */
    public function get(string $id): object;

    public function has(string $id): bool;

    /**
     * @template U of object
     * @param class-string<U> $id
     * @param callable(Container<T>): U $definition
     */
    public function set(string $id, callable $definition): void;

    /**
     * @template U of object
     * @param class-string<U> $interface
     * @param class-string<U> $concrete
     */
    public function bind(string $interface, string $concrete): void;
}
