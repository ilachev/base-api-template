<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Repository;

use App\Domain\Entity;
use App\Domain\EntityRepository;
use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Storage;

/**
 * Base implementation of EntityRepository that can be used for any entity.
 */
abstract class BaseRepository extends AbstractRepository implements EntityRepository
{
    private string $tableName;

    private string $primaryKey;

    public function __construct(
        Storage $storage,
        Hydrator $hydrator,
        QueryFactory $queryBuilderFactory,
        string $tableName,
        string $primaryKey = 'id',
    ) {
        parent::__construct($storage, $hydrator, $queryBuilderFactory);
        $this->tableName = $tableName;
        $this->primaryKey = $primaryKey;
    }

    final public function findById(string $className, mixed $id): ?Entity
    {
        $query = $this->query($this->tableName)
            ->where($this->primaryKey, $id);

        return $this->fetchOne($className, $query);
    }

    final public function save(Entity $entity): Entity
    {
        return $this->saveEntity($entity, $this->tableName, $this->primaryKey, $entity->id);
    }
}
