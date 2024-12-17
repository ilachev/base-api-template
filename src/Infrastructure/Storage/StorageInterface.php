<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

interface StorageInterface
{
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
    public function transaction(callable $callback): mixed;
    public function lastInsertId(): string;
}
