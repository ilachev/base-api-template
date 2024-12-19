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

    /**
     * @param array<string, scalar|null> $params
     * @return list<array<string, scalar|null>>
     */
    public function query(string $sql, array $params = []): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        /** @var array<array-key, array<string, scalar|null>> $result */
        $result = $statement->fetchAll();

        return array_values($result);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function execute(string $sql, array $params = []): bool
    {
        $statement = $this->connection->prepare($sql);

        return $statement->execute($params);
    }
}
