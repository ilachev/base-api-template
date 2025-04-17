<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

use App\Infrastructure\Storage\StorageException;

final class SQLiteQueryBuilder extends BaseQueryBuilder
{
    public static function table(string $table): self
    {
        $instance = new self();
        $instance->table = $table;

        return $instance;
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    public function buildSelectQuery(): array
    {
        $query = 'SELECT ' . implode(', ', $this->select) . " FROM {$this->table}";

        if (!empty($this->where)) {
            $query .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->orderBy)) {
            $query .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $query .= " OFFSET {$this->offset}";
        }

        return [$query, $this->params];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{string, array<string, mixed>}
     */
    public function buildInsertQuery(array $data): array
    {
        if (empty($data)) {
            throw new StorageException('Cannot insert empty data');
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(static fn(string $key) => ":{$key}", array_keys($data)));

        $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        return [$query, $data];
    }

    /**
     * @param array<string, mixed> $data
     * @param string $primaryKey Primary key column name
     * @return array{string, array<string, mixed>}
     */
    public function buildUpsertQuery(array $data, string $primaryKey): array
    {
        if (empty($data)) {
            throw new StorageException('Cannot insert or update empty data');
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(static fn(string $key) => ":{$key}", array_keys($data)));

        // SQLite syntax for upsert
        $updateSets = array_map(
            static fn(string $key) => "{$key} = excluded.{$key}",
            array_keys($data),
        );

        $updateClause = implode(', ', $updateSets);

        $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders}) "
             . "ON CONFLICT({$primaryKey}) DO UPDATE SET {$updateClause}";

        return [$query, $data];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{string, array<string, mixed>}
     */
    public function buildUpdateQuery(array $data): array
    {
        if (empty($data)) {
            throw new StorageException('Cannot update with empty data');
        }

        if (empty($this->where)) {
            throw new StorageException('Update query must have WHERE clause for safety');
        }

        $sets = array_map(
            static fn(string $key) => "{$key} = :{$key}",
            array_keys($data),
        );

        $query = "UPDATE {$this->table} SET " . implode(', ', $sets);

        $query .= ' WHERE ' . implode(' AND ', $this->where);

        return [$query, array_merge($data, $this->params)];
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    public function buildDeleteQuery(): array
    {
        if (empty($this->where)) {
            throw new StorageException('Delete query must have WHERE clause for safety');
        }

        $query = "DELETE FROM {$this->table}";
        $query .= ' WHERE ' . implode(' AND ', $this->where);

        return [$query, $this->params];
    }
}
