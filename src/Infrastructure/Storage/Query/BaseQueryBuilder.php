<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

use App\Infrastructure\Storage\StorageException;

abstract class BaseQueryBuilder implements QueryBuilder
{
    /** @var array<string> */
    protected array $select = ['*'];

    protected string $table = '';

    /** @var array<string, mixed> */
    protected array $params = [];

    /** @var array<string> */
    protected array $where = [];

    /** @var array<string> */
    protected array $orderBy = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    /**
     * @param string|array<string> $columns
     */
    final public function select(string|array $columns): static
    {
        $this->select = \is_array($columns) ? $columns : [$columns];

        return $this;
    }

    final public function where(string $column, mixed $value, string $operator = '='): static
    {
        $paramName = $this->generateParamName($column);
        $this->where[] = "{$column} {$operator} :{$paramName}";
        $this->params[$paramName] = $value;

        return $this;
    }

    /**
     * @param array<mixed> $values
     */
    final public function whereIn(string $column, array $values): static
    {
        if (empty($values)) {
            throw new StorageException('Cannot use whereIn with empty values array');
        }

        $placeholders = [];

        foreach ($values as $i => $value) {
            $paramName = "{$column}_in_{$i}";
            $placeholders[] = ":{$paramName}";
            $this->params[$paramName] = $value;
        }

        $this->where[] = "{$column} IN (" . implode(', ', $placeholders) . ')';

        return $this;
    }

    final public function whereRaw(string $condition, array $params = []): static
    {
        $this->where[] = $condition;
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    final public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction);
        if (!\in_array($direction, ['ASC', 'DESC'], true)) {
            throw new StorageException('Order direction must be ASC or DESC');
        }

        $this->orderBy[] = "{$column} {$direction}";

        return $this;
    }

    final public function limit(int $limit): static
    {
        if ($limit < 0) {
            throw new StorageException('Limit cannot be negative');
        }

        $this->limit = $limit;

        return $this;
    }

    final public function offset(int $offset): static
    {
        if ($offset < 0) {
            throw new StorageException('Offset cannot be negative');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * Преобразует имена полей из camelCase в snake_case.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    final public static function camelToSnakeKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $snakeKey = self::camelToSnake($key);
            $result[$snakeKey] = $value;
        }

        return $result;
    }

    /**
     * Преобразует имена полей из snake_case в camelCase.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    final public static function snakeToCamelKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $camelKey = self::snakeToCamel($key);
            $result[$camelKey] = $value;
        }

        return $result;
    }

    /**
     * Преобразует строку из camelCase в snake_case.
     */
    protected static function camelToSnake(string $input): string
    {
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);

        return strtolower($replaced ?? $input);
    }

    /**
     * Преобразует строку из snake_case в camelCase.
     */
    protected static function snakeToCamel(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }

    protected function generateParamName(string $column): string
    {
        $normalized = str_replace('.', '_', $column);
        $count = 0;

        foreach (array_keys($this->params) as $existing) {
            if (str_starts_with($existing, $normalized)) {
                ++$count;
            }
        }

        return $count > 0 ? "{$normalized}_{$count}" : $normalized;
    }
}
