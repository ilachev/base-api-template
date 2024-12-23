<?php

declare(strict_types=1);

namespace App\Infrastructure\DI;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;
use ReflectionNamedType;

/**
 * @template-covariant T of object
 */
class Container implements ContainerInterface
{
    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var array<string, callable(ContainerInterface): object>
     */
    private array $definitions = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * @var array<string, bool>
     */
    private array $resolving = [];

    /**
     * @template U of object
     * @param class-string<U> $id
     * @param callable(ContainerInterface): U $definition
     */
    public function set(string $id, callable $definition): void
    {
        $this->definitions[$id] = $definition;
    }

    /**
     * @template U of object
     * @param class-string<U> $interface
     * @param class-string<U> $concrete
     */
    public function bind(string $interface, string $concrete): void
    {
        $this->aliases[$interface] = $concrete;
    }

    /**
     * @template U of object
     * @param class-string<U> $id
     * @return U
     * @throws ContainerException
     */
    public function get(string $id): object
    {
        $concrete = $this->aliases[$id] ?? $id;

        if (isset($this->instances[$concrete])) {
            /** @var U */
            return $this->instances[$concrete];
        }

        if (isset($this->resolving[$concrete])) {
            throw new ContainerException("Circular dependency detected for $concrete");
        }

        $this->resolving[$concrete] = true;

        try {
            if (isset($this->definitions[$concrete])) {
                $instance = ($this->definitions[$concrete])($this);
            } else {
                /** @var class-string<U> $concrete */
                $instance = $this->resolve($concrete);
            }

            $this->instances[$concrete] = $instance;
            /** @var U */
            return $instance;
        } finally {
            unset($this->resolving[$concrete]);
        }
    }

    public function has(string $id): bool
    {
        $concrete = $this->aliases[$id] ?? $id;
        return isset($this->instances[$concrete]) || isset($this->definitions[$concrete]);
    }

    /**
     * @template U of object
     * @param class-string<U> $id
     * @return U
     * @throws ContainerException
     */
    private function resolve(string $id): object
    {
        if (!interface_exists($id) && !class_exists($id)) {
            throw new ContainerException("Class or interface $id does not exist");
        }

        if (interface_exists($id) && !isset($this->aliases[$id])) {
            throw new ContainerException("No binding found for interface $id");
        }

        $reflection = new ReflectionClass($id);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class $id is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            /** @var U */
            return new $id();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        try {
            /** @var U */
            return new $id(...$dependencies);
        } catch (\Throwable $e) {
            throw new ContainerException("Cannot instantiate $id", 0, $e);
        }
    }

    /**
     * @param ReflectionParameter[] $parameters
     * @return array<int, mixed>
     * @throws ContainerException
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $parameter->getType();

            if ($dependency === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new ContainerException(
                    "Cannot resolve parameter {$parameter->getName()}: no type hint"
                );
            }

            if (!$dependency instanceof ReflectionNamedType) {
                throw new ContainerException(
                    "Cannot resolve union or intersection type for parameter {$parameter->getName()}"
                );
            }

            if ($dependency->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new ContainerException(
                    "Cannot resolve built-in type for parameter {$parameter->getName()}"
                );
            }

            /** @var class-string $dependencyName */
            $dependencyName = $dependency->getName();
            $dependencies[] = $this->get($dependencyName);
        }

        return $dependencies;
    }
}
