<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

final class SQLiteStorage extends AbstractStorage
{
    /** @var array<string, \PDOStatement> */
    private array $preparedStatements = [];

    public function __construct(string $path)
    {
        $this->connection = new \PDO("sqlite:{$path}");

        $this->connection->exec('PRAGMA journal_mode = WAL');
        $this->connection->exec('PRAGMA read_uncommitted = ON');
        $this->connection->exec('PRAGMA cache_size = -16000');  // Увеличен размер кеша (~16MB)
        $this->connection->exec('PRAGMA synchronous = NORMAL');
        $this->connection->exec('PRAGMA page_size = 8192');     // Увеличен размер страницы
        $this->connection->exec('PRAGMA wal_autocheckpoint = 2000'); // Реже делать checkpoint
        $this->connection->exec('PRAGMA mmap_size = 268435456'); // ~256MB для mmap
        $this->connection->exec('PRAGMA temp_store = MEMORY');  // Временные таблицы в памяти
        $this->connection->exec('PRAGMA foreign_keys = ON');

        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); // Использовать нативные prepared statements
    }

    /**
     * @param array<string, scalar|null> $params
     * @return list<array<string, scalar|null>>
     * @throws StorageException
     */
    public function query(string $sql, array $params = []): array
    {
        try {
            $statement = $this->getStatement($sql);
            $statement->execute($params);
            /** @var array<array-key, array<string, scalar|null>> $result */
            $result = $statement->fetchAll();

            return array_values($result);
        } catch (\PDOException $e) {
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
            $statement = $this->getStatement($sql);

            return $statement->execute($params);
        } catch (\PDOException $e) {
            throw new StorageException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает подготовленный запрос или создает новый, если его нет в кеше.
     * Кеширует часто используемые запросы для повышения производительности.
     *
     * @throws \PDOException
     */
    private function getStatement(string $sql): \PDOStatement
    {
        // Хешируем SQL для использования в качестве ключа кеша
        $key = md5($sql);

        if (!isset($this->preparedStatements[$key])) {
            // Только если заявления нет в кеше, создаем новое
            $this->preparedStatements[$key] = $this->connection->prepare($sql);

            // Ограничиваем размер кеша для предотвращения утечек памяти
            if (\count($this->preparedStatements) > 100) {
                // Удаляем первый элемент (самый старый) в случае переполнения
                array_shift($this->preparedStatements);
            }
        }

        return $this->preparedStatements[$key];
    }
}
