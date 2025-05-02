<?php

declare(strict_types=1);

namespace App\Domain;

interface EntityRepository
{
    /**
     * Find entity by ID.
     *
     * @template T of Entity
     * @param class-string<T> $className
     * @return T|null
     */
    public function findById(string $className, mixed $id): ?Entity;

    /**
     * Save entity and return the updated instance with ID.
     *
     * @template T of Entity
     * @param T $entity
     * @return T
     */
    public function save(Entity $entity): Entity;
}
