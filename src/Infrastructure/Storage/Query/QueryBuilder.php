<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

interface QueryBuilder
{
    /**
     * @param string|array<string> $columns
     */
    public function select(string|array $columns): static;

    public function where(string $column, mixed $value, string $operator = '='): static;

    /**
     * @param array<mixed> $values
     */
    public function whereIn(string $column, array $values): static;

    /**
     * @param array<string, mixed> $params
     */
    public function whereRaw(string $condition, array $params = []): static;

    public function orderBy(string $column, string $direction = 'ASC'): static;

    public function limit(int $limit): static;

    public function offset(int $offset): static;

    /**
     * @return array{string, array<string, mixed>}
     */
    public function buildSelectQuery(): array;

    /**
     * @param array<string, mixed> $data
     * @return array{string, array<string, mixed>}
     */
    public function buildInsertQuery(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array{string, array<string, mixed>}
     */
    public function buildUpdateQuery(array $data): array;

    /**
     * @return array{string, array<string, mixed>}
     */
    public function buildDeleteQuery(): array;
}
