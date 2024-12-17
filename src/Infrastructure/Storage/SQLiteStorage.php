<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use PDO;

final class SQLiteStorage extends AbstractStorage
{
    public function __construct(string $path)
    {
        $this->connection = new PDO("sqlite:{$path}");
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function query(string $sql, array $params = []): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool
    {
        $statement = $this->connection->prepare($sql);

        return $statement->execute($params);
    }
}
