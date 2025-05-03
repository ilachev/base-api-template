<?php

declare(strict_types=1);

namespace App\Infrastructure\DI;

/**
 * @template-covariant T of object
 * @implements Container<T>
 */
final class DIContainer implements Container
{
    /**
     * @var array<class-string, object>
     */
    private array $instances = [];

    /**
     * @var array<class-string, callable(Container<T>): object>
     */
    private array $definitions = [];

    /**
     * @var array<class-string, class-string>
     */
    private array $aliases = [];

    /**
     * @var array<class-string, bool>
     */
    private array $resolving = [];

    /**
     * @template U of object
     * @param class-string<U> $id
     * @param callable(Container<T>): U $definition
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

        // Clear cached instance of this interface when binding changes
        if (isset($this->instances[$interface])) {
            unset($this->instances[$interface]);
        }
    }

    /**
     * @template U of object
     * @param class-string<U> $id
     * @return U
     * @throws ContainerException
     */
    public function get(string $id): object
    {
        // First check if we have a cached instance
        if (isset($this->instances[$id])) {
            /** @var U */
            return $this->instances[$id];
        }

        // Get concrete implementation if this is an interface with binding
        $concrete = $this->aliases[$id] ?? $id;

        // Check if concrete instance is already resolved
        if (isset($this->instances[$concrete]) && $id !== $concrete) {
            $this->instances[$id] = $this->instances[$concrete];

            /** @var U */
            return $this->instances[$id];
        }

        // Circular dependency check
        if (isset($this->resolving[$concrete])) {
            throw new ContainerException("Circular dependency detected for {$concrete}");
        }

        $this->resolving[$concrete] = true;

        try {
            if (isset($this->definitions[$concrete])) {
                // If we have a factory for this concrete class, use it
                $instance = ($this->definitions[$concrete])($this);
            } else {
                // Otherwise resolve all dependencies recursively
                /** @var class-string<U> $concrete */
                $instance = $this->resolve($concrete);
            }

            // Cache instance under both interface and concrete name
            $this->instances[$concrete] = $instance;
            if ($id !== $concrete) {
                $this->instances[$id] = $instance;
            }

            /** @var U */
            return $instance;
        } finally {
            unset($this->resolving[$concrete]);
        }
    }

    public function has(string $id): bool
    {
        // Direct check if this is an interface with a binding
        if (interface_exists($id) && isset($this->aliases[$id])) {
            return true;
        }

        // Check if we have the concrete implementation
        $concrete = $this->aliases[$id] ?? $id;

        return isset($this->instances[$concrete])
            || isset($this->definitions[$concrete]);
    }

    /**
     * @template U of object
     * @param class-string<U> $concrete
     * @throws ContainerException
     */
    private function resolve(string $concrete): object
    {
        if (!interface_exists($concrete) && !class_exists($concrete)) {
            throw new ContainerException("Class or interface {$concrete} does not exist");
        }

        // Handle interface resolution
        if (interface_exists($concrete)) {
            if (!isset($this->aliases[$concrete])) {
                throw new ContainerException("No binding found for interface {$concrete}");
            }

            // Get the concrete implementation for this interface
            /** @var class-string<U> $aliasConcrete */
            $aliasConcrete = $this->aliases[$concrete];

            // Check if we already have the concrete class instance
            if (isset($this->instances[$aliasConcrete])) {
                return $this->instances[$aliasConcrete];
            }

            // Resolve the concrete class, not the interface
            return $this->resolve($aliasConcrete);
        }

        $reflection = new \ReflectionClass($concrete);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class {$concrete} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            /** @var U */
            return new $concrete();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        try {
            /** @var U */
            return new $concrete(...$dependencies);
        } catch (\Throwable $e) {
            throw new ContainerException("Cannot instantiate {$concrete}", 0, $e);
        }
    }

    /**
     * @param \ReflectionParameter[] $parameters
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
                    "Cannot resolve parameter {$parameter->getName()}: no type hint",
                );
            }

            if (!$dependency instanceof \ReflectionNamedType) {
                throw new ContainerException(
                    "Cannot resolve union or intersection type for parameter {$parameter->getName()}",
                );
            }

            if ($dependency->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();

                    continue;
                }

                throw new ContainerException(
                    "Cannot resolve built-in type for parameter {$parameter->getName()}",
                );
            }

            /** @var class-string $dependencyName */
            $dependencyName = $dependency->getName();
            $dependencies[] = $this->get($dependencyName);
        }

        return $dependencies;
    }
}
