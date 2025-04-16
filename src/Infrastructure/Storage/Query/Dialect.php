<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Query;

interface Dialect
{
    /**
     * Форматирует имя таблицы в соответствии с диалектом
     */
    public function quoteTable(string $table): string;

    /**
     * Форматирует имя столбца в соответствии с диалектом
     */
    public function quoteColumn(string $column): string;

    /**
     * Возвращает синтаксис запроса LIMIT/OFFSET.
     */
    public function limit(?int $limit, ?int $offset = null): string;

    /**
     * Возвращает запрос на получение последнего вставленного ID.
     */
    public function lastInsertIdQuery(string $table, string $primaryKey = 'id'): string;

    /**
     * Преобразует имена полей из camelCase в формат, соответствующий диалекту.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function convertFieldNames(array $data): array;
}
