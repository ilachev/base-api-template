<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

final readonly class PostgreSQLDialect implements Dialect
{
    public function __construct(
        private string $schema = 'public',
    ) {}

    public function quoteTable(string $table): string
    {
        return "\"{$this->schema}\".\"{$table}\"";
    }

    public function quoteColumn(string $column): string
    {
        // If it contains a dot, it might be a fully qualified column name
        if (str_contains($column, '.')) {
            [$table, $col] = explode('.', $column, 2);

            return "\"{$table}\".\"{$col}\"";
        }

        return "\"{$column}\"";
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
        return 'SELECT lastval()';
    }

    /**
     * Converts field names from camelCase to snake_case.
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
     * Converts string from camelCase to snake_case.
     */
    private function camelToSnake(string $input): string
    {
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);

        return strtolower($replaced ?? $input);
    }
}
