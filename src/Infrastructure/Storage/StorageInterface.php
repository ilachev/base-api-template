<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

interface StorageInterface
{
    /**
     * @param array<string, scalar|null> $params
     * @return list<array<string, scalar|null>>
     */
    public function query(string $sql, array $params = []): array;

    /**
     * @param array<string, scalar|null> $params
     */
    public function execute(string $sql, array $params = []): bool;

    public function transaction(callable $callback): mixed;

    public function lastInsertId(): string;
}
