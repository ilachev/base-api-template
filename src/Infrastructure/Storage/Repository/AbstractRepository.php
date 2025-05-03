<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Repository;

use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Storage\Query\QueryBuilder;
use App\Infrastructure\Storage\Query\QueryFactory;
use App\Infrastructure\Storage\Storage;

abstract class AbstractRepository
{
    public function __construct(
        protected readonly Storage $storage,
        protected readonly Hydrator $hydrator,
        protected readonly QueryFactory $queryBuilderFactory,
    ) {}

    /**
     * Получить построитель запросов для указанной таблицы.
     */
    protected function query(string $table): QueryBuilder
    {
        return $this->queryBuilderFactory->createQueryBuilder($table);
    }

    /**
     * Создать объект сущности из данных БД.
     *
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $data
     * @return T
     */
    protected function createEntity(string $className, array $data): object
    {
        $normalizedData = $this->snakeToCamelKeys($data);

        return $this->hydrator->hydrate($className, $normalizedData);
    }

    /**
     * Извлечь данные из объекта и преобразовать для БД.
     *
     * @return array<string, mixed>
     */
    protected function extractEntityData(object $entity): array
    {
        $data = $this->hydrator->extract($entity);

        /** @var array<string, mixed> $extractedData */
        $extractedData = $data;

        return $this->camelToSnakeKeys($extractedData);
    }

    /**
     * Выполнить запрос и вернуть один объект
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T|null
     */
    protected function fetchOne(string $className, QueryBuilder $queryBuilder): ?object
    {
        $queryBuilder->limit(1);
        [$sql, $params] = $queryBuilder->buildSelectQuery();

        /** @var array<string, scalar|null> $castParams */
        $castParams = $params;
        $result = $this->storage->query($sql, $castParams);

        if (empty($result)) {
            return null;
        }

        return $this->createEntity($className, $result[0]);
    }

    /**
     * Выполнить запрос и вернуть несколько объектов.
     *
     * @template T of object
     * @param class-string<T> $className
     * @return array<T>
     */
    protected function fetchAll(string $className, QueryBuilder $queryBuilder): array
    {
        [$sql, $params] = $queryBuilder->buildSelectQuery();

        /** @var array<string, scalar|null> $castParams */
        $castParams = $params;
        $result = $this->storage->query($sql, $castParams);

        return array_map(
            fn(array $row) => $this->createEntity($className, $row),
            $result,
        );
    }

    /**
     * Сохранить объект в БД.
     *
     * @template T of object
     * @param T $entity
     * @return T
     */
    protected function saveEntity(object $entity, string $table, string $primaryKey, mixed $primaryKeyValue): object
    {
        $data = $this->extractEntityData($entity);
        $isInsert = $primaryKeyValue === null;

        if ($isInsert) {
            // For inserts with auto-incrementing primary keys, we should remove the ID field
            // so the database can assign it automatically
            unset($data[$primaryKey]);

            $insertQuery = $this->query($table);
            [$sql, $params] = $insertQuery->buildInsertQuery($data);
        } else {
            // For updates, make sure the primary key is included
            if (!isset($data[$primaryKey])) {
                // We know $primaryKeyValue is not null here since $isInsert would be true otherwise
                $data[$primaryKey] = $primaryKeyValue;
            }

            $insertQuery = $this->query($table);
            [$sql, $params] = $insertQuery->buildUpsertQuery($data, $primaryKey);
        }

        /** @var array<string, scalar|null> $castParams */
        $castParams = $params;
        $this->storage->execute($sql, $castParams);

        // If this was an insert, update the entity with the new ID
        if ($isInsert) {
            $data = $this->hydrator->extract($entity);
            $data[$primaryKey] = $this->storage->lastInsertId();

            return $this->hydrator->hydrate($entity::class, $data);
        }

        return $entity;
    }

    /**
     * Удалить запись из БД.
     */
    protected function deleteEntity(string $table, string $primaryKey, mixed $primaryKeyValue): void
    {
        $deleteQuery = $this->query($table)
            ->where($primaryKey, $primaryKeyValue);

        [$sql, $params] = $deleteQuery->buildDeleteQuery();
        /** @var array<string, scalar|null> $castParams */
        $castParams = $params;
        $this->storage->execute($sql, $castParams);
    }

    /**
     * Преобразует имена полей из camelCase в snake_case.
     *
     * @param array<string, mixed> $data
     * @return array<string, scalar|null>
     */
    protected function camelToSnakeKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $snakeKey = $this->camelToSnake($key);
            // Cast to scalar or null
            if (\is_scalar($value) || $value === null) {
                $result[$snakeKey] = $value;
            } elseif (\is_object($value) && method_exists($value, '__toString')) {
                $result[$snakeKey] = (string) $value;
            } else {
                // Convert to string or null for non-scalar values
                $result[$snakeKey] = \is_array($value) ? json_encode($value) : null;
            }
        }

        /** @var array<string, scalar|null> $result */
        return $result;
    }

    /**
     * Преобразует имена полей из snake_case в camelCase.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function snakeToCamelKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $camelKey = $this->snakeToCamel($key);
            $result[$camelKey] = $value;
        }

        return $result;
    }

    /**
     * Преобразует строку из camelCase в snake_case.
     */
    protected function camelToSnake(string $input): string
    {
        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);

        return strtolower($result !== null ? $result : $input);
    }

    /**
     * Преобразует строку из snake_case в camelCase.
     */
    protected function snakeToCamel(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }
}
