<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use PDO;

abstract class AbstractStorage implements StorageInterface
{
    protected PDO $connection;

    public function transaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $callback();
            $this->connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }
}
