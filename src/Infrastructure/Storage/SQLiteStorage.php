<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use PDO;
use PDOException;

final class SQLiteStorage extends AbstractStorage
{
    public function __construct(string $path)
    {
        $this->connection = new PDO("sqlite:{$path}");

        $this->connection->exec('PRAGMA journal_mode = WAL');
        $this->connection->exec('PRAGMA read_uncommitted = ON');
        $this->connection->exec('PRAGMA cache_size = -4000');
        $this->connection->exec('PRAGMA synchronous = NORMAL');
        $this->connection->exec('PRAGMA page_size = 4096');
        $this->connection->exec('PRAGMA wal_autocheckpoint = 1000');

        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, scalar|null> $params
     * @return list<array<string, scalar|null>>
     * @throws StorageException
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);
            /** @var array<array-key, array<string, scalar|null>> $result */
            $result = $statement->fetchAll();

            return array_values($result);
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, scalar|null> $params
     * @throws StorageException
     */
    public function execute(string $sql, array $params = []): bool
    {
        try {
            $statement = $this->connection->prepare($sql);

            return $statement->execute($params);
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }
}
