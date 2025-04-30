<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

use App\Infrastructure\Storage\StorageException;

final class PostgreSQLQueryBuilder extends BaseQueryBuilder
{
    private readonly PostgreSQLDialect $dialect;

    /**
     * Creates a new query builder for the specified table.
     */
    public static function table(string $table, string $schema = 'public'): self
    {
        $instance = new self($schema);
        $instance->table = $table;

        return $instance;
    }

    public function __construct(string $schema = 'public')
    {
        $this->dialect = new PostgreSQLDialect($schema);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function buildSelectQuery(): array
    {
        $quotedTable = $this->dialect->quoteTable($this->table);
        $columns = implode(', ', array_map(fn($column) => $column === '*' ? $column : $this->dialect->quoteColumn($column), $this->select));

        $sql = "SELECT {$columns} FROM {$quotedTable}";

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        $sql .= $this->dialect->limit($this->limit, $this->offset);

        return [$sql, $this->params];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function buildInsertQuery(array $data, string $primaryKey = 'id'): array
    {
        if (empty($data)) {
            throw new StorageException('Cannot insert with empty data');
        }

        $quotedTable = $this->dialect->quoteTable($this->table);
        $normalizedData = $this->dialect->convertFieldNames($data);

        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($normalizedData as $column => $value) {
            $columns[] = $this->dialect->quoteColumn($column);
            $paramName = ":{$column}";
            $placeholders[] = $paramName;
            $params[$column] = $value;
        }

        $columnList = implode(', ', $columns);
        $placeholderList = implode(', ', $placeholders);

        $sql = "INSERT INTO {$quotedTable} ({$columnList}) VALUES ({$placeholderList}) RETURNING {$primaryKey}";

        return [$sql, $params];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function buildUpdateQuery(array $data, string $primaryKey = 'id'): array
    {
        if (empty($data)) {
            throw new StorageException('Cannot update with empty data');
        }

        if (empty($this->where)) {
            throw new StorageException('Cannot update without WHERE clause');
        }

        $quotedTable = $this->dialect->quoteTable($this->table);
        $normalizedData = $this->dialect->convertFieldNames($data);

        $setStatements = [];
        $params = [];

        foreach ($normalizedData as $column => $value) {
            if ($column === $primaryKey) {
                continue; // Skip primary key in SET statement
            }

            $quotedColumn = $this->dialect->quoteColumn($column);
            $paramName = "set_{$column}";
            $setStatements[] = "{$quotedColumn} = :{$paramName}";
            $params[$paramName] = $value;
        }

        if (empty($setStatements)) {
            throw new StorageException('No columns to update after removing primary key');
        }

        $sql = "UPDATE {$quotedTable} SET " . implode(', ', $setStatements);

        $sql .= ' WHERE ' . implode(' AND ', $this->where);

        return [$sql, array_merge($params, $this->params)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function buildUpsertQuery(array $data, string $primaryKey = 'id'): array
    {
        if (empty($data)) {
            throw new StorageException('Cannot upsert with empty data');
        }

        $quotedTable = $this->dialect->quoteTable($this->table);
        $normalizedData = $this->dialect->convertFieldNames($data);

        $columns = [];
        $placeholders = [];
        $updateParts = [];
        $params = [];

        foreach ($normalizedData as $column => $value) {
            $quotedColumn = $this->dialect->quoteColumn($column);
            $columns[] = $quotedColumn;

            $paramName = "upsert_{$column}";
            $placeholders[] = ":{$paramName}";
            $params[$paramName] = $value;

            if ($column !== $primaryKey) {
                $updateParts[] = "{$quotedColumn} = EXCLUDED.{$column}";
            }
        }

        $columnList = implode(', ', $columns);
        $placeholderList = implode(', ', $placeholders);
        $updateList = implode(', ', $updateParts);

        $sql = "INSERT INTO {$quotedTable} ({$columnList}) VALUES ({$placeholderList}) "
               . "ON CONFLICT ({$primaryKey}) DO UPDATE SET {$updateList}";

        return [$sql, $params];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function buildDeleteQuery(): array
    {
        if (empty($this->where)) {
            throw new StorageException('Cannot delete without WHERE clause');
        }

        $quotedTable = $this->dialect->quoteTable($this->table);

        $sql = "DELETE FROM {$quotedTable} WHERE " . implode(' AND ', $this->where);

        return [$sql, $this->params];
    }
}
