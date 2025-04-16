<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

final readonly class SQLiteDialect implements Dialect
{
    public function quoteTable(string $table): string
    {
        return $table;
    }

    public function quoteColumn(string $column): string
    {
        return $column;
    }

    public function limit(?int $limit, ?int $offset = null): string
    {
        if ($limit === null) {
            return '';
        }

        $sql = " LIMIT {$limit}";

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return $sql;
    }

    public function lastInsertIdQuery(string $table, string $primaryKey = 'id'): string
    {
        return 'SELECT last_insert_rowid()';
    }

    /**
     * Преобразует имена полей из camelCase в snake_case.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function convertFieldNames(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $snakeKey = $this->camelToSnake($key);
            $result[$snakeKey] = $value;
        }

        return $result;
    }

    /**
     * Преобразует строку из camelCase в snake_case.
     */
    private function camelToSnake(string $input): string
    {
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);

        return strtolower($replaced ?? $input);
    }
}
