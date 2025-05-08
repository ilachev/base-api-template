<?php

declare(strict_types=1);

namespace ProtoPhpGen\Infrastructure\Hydrator;

/**
 * Interface for hydrators that work with a specific entity type.
 * This is a reference implementation that should match the one in the main project.
 */
interface TypedHydrator
{
    /**
     * Get the entity class this hydrator can handle.
     *
     * @return string Class name of the entity
     */
    public function getEntityClass(): string;

    /**
     * Create an entity from array data.
     *
     * @param array<string, mixed> $data Array of entity data
     * @return object Entity instance
     */
    public function hydrate(array $data): object;

    /**
     * Extract data from entity to array.
     *
     * @param object $entity Entity to extract data from
     * @return array<string, mixed> Array of entity data
     */
    public function extract(object $entity): array;
}
