<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage\Session;

use App\Domain\Session\Session;
use App\Infrastructure\Hydrator\Hydrator;
use App\Infrastructure\Hydrator\HydratorInterface;
use App\Infrastructure\Storage\Query\QueryBuilderFactory;
use App\Infrastructure\Storage\Session\SQLiteSessionRepository;
use App\Infrastructure\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;

final class SQLiteSessionRepositoryTest extends TestCase
{
    private SQLiteSessionRepository $repository;

    private InMemoryTestStorage $storage;

    private HydratorInterface $hydrator;

    private QueryBuilderFactory $queryBuilderFactory;

    protected function setUp(): void
    {
        $this->storage = new InMemoryTestStorage();
        $this->hydrator = new Hydrator();
        $this->queryBuilderFactory = new QueryBuilderFactory();

        $this->repository = new SQLiteSessionRepository(
            $this->storage,
            $this->hydrator,
            $this->queryBuilderFactory,
        );
    }

    public function testFindByIdReturnsSessionWhenFound(): void
    {
        // Setup test data
        $sessionData = [
            'id' => 'test-session-id',
            'user_id' => 1,
            'payload' => '{}',
            'expires_at' => time() + 3600,
            'created_at' => time() - 100,
            'updated_at' => time() - 50,
        ];

        $this->storage->addRow('sessions', $sessionData);

        // Execute test
        $result = $this->repository->findById('test-session-id');

        // Verify results
        self::assertNotNull($result);
        self::assertInstanceOf(Session::class, $result);
        self::assertSame('test-session-id', $result->id);
        self::assertSame(1, $result->userId);
        self::assertSame('{}', $result->payload);
        self::assertSame($sessionData['expires_at'], $result->expiresAt);
        self::assertSame($sessionData['created_at'], $result->createdAt);
        self::assertSame($sessionData['updated_at'], $result->updatedAt);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        // No setup needed, storage is empty

        // Execute test
        $result = $this->repository->findById('non-existent-id');

        // Verify results
        self::assertNull($result);
    }

    public function testFindByUserIdReturnsSessionsForUser(): void
    {
        // Setup test data
        $sessionData1 = [
            'id' => 'session-1',
            'user_id' => 10,
            'payload' => '{}',
            'expires_at' => time() + 3600,
            'created_at' => time() - 100,
            'updated_at' => time() - 50,
        ];

        $sessionData2 = [
            'id' => 'session-2',
            'user_id' => 10,
            'payload' => '{}',
            'expires_at' => time() + 3600,
            'created_at' => time() - 80,
            'updated_at' => time() - 30,
        ];

        $otherUserSessionData = [
            'id' => 'session-3',
            'user_id' => 20,
            'payload' => '{}',
            'expires_at' => time() + 3600,
            'created_at' => time() - 60,
            'updated_at' => time() - 20,
        ];

        $this->storage->addRow('sessions', $sessionData1);
        $this->storage->addRow('sessions', $sessionData2);
        $this->storage->addRow('sessions', $otherUserSessionData);

        // Execute test
        $results = $this->repository->findByUserId(10);

        // Verify results
        self::assertCount(2, $results);
        self::assertSame('session-1', $results[0]->id);
        self::assertSame('session-2', $results[1]->id);
    }

    public function testDeleteExpiredRemovesExpiredSessions(): void
    {
        // Setup test data
        $now = time();

        $validSessionData = [
            'id' => 'valid-session',
            'user_id' => 1,
            'payload' => '{}',
            'expires_at' => $now + 3600,
            'created_at' => $now - 100,
            'updated_at' => $now - 50,
        ];

        $expiredSessionData = [
            'id' => 'expired-session',
            'user_id' => 1,
            'payload' => '{}',
            'expires_at' => $now - 3600,
            'created_at' => $now - 10000,
            'updated_at' => $now - 5000,
        ];

        $this->storage->addRow('sessions', $validSessionData);
        $this->storage->addRow('sessions', $expiredSessionData);

        // Verify setup
        self::assertCount(2, $this->storage->getTables()['sessions']);

        // Execute test
        $this->repository->deleteExpired();

        // Verify results
        self::assertCount(1, $this->storage->getTables()['sessions']);
        self::assertSame('valid-session', $this->storage->getTables()['sessions'][0]['id']);
    }
}

/**
 * Test implementation of StorageInterface that stores data in memory.
 */
final class InMemoryTestStorage implements StorageInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $tables = [];

    /**
     * @param array<string, mixed> $data
     */
    public function addRow(string $tableName, array $data): void
    {
        if (!isset($this->tables[$tableName])) {
            $this->tables[$tableName] = [];
        }

        $this->tables[$tableName][] = $data;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @param array<string, bool|float|int|string|null> $params
     * @return list<array<string, bool|float|int|string|null>>
     */
    public function query(string $sql, array $params = []): array
    {
        if (str_starts_with(trim($sql), 'SELECT COUNT(*) as count')) {
            // Handle count query for checking existence
            preg_match('/FROM\s+(\w+)\s+WHERE\s+(\w+)\s+=\s+:(\w+)/i', $sql, $matches);
            if (\count($matches) >= 4) {
                $tableName = $matches[1];
                $column = $matches[2];
                $paramName = $matches[3];

                if (!isset($this->tables[$tableName])) {
                    return [['count' => 0]];
                }

                $count = 0;
                foreach ($this->tables[$tableName] as $row) {
                    if (isset($row[$column]) && $row[$column] === $params[$paramName]) {
                        ++$count;
                    }
                }

                return [['count' => $count]];
            }
        } elseif (preg_match('/SELECT.*FROM\s+(\w+)/i', $sql, $matches)) {
            $tableName = $matches[1];

            if (!isset($this->tables[$tableName])) {
                return [];
            }

            $results = $this->tables[$tableName];

            // Handle WHERE conditions
            if (preg_match('/WHERE\s+(.*?)(?:ORDER BY|LIMIT|$)/is', $sql, $whereMatches)) {
                $whereClause = $whereMatches[1];

                // Parse simple equals condition
                if (preg_match('/(\w+)\s*=\s*:(\w+)/i', $whereClause, $condMatches)) {
                    $column = $condMatches[1];
                    $paramName = $condMatches[2];

                    $results = array_filter($results, static fn($row) => isset($row[$column]) && $row[$column] === $params[$paramName]);
                }

                // Parse expires_at condition for deleteExpired
                if (preg_match('/expires_at\s*<\s*:current_time/i', $whereClause)) {
                    $results = array_filter($results, static fn($row) => isset($row['expires_at']) && $row['expires_at'] < $params['current_time']);
                }
            }

            // Handle LIMIT (simple implementation)
            if (preg_match('/LIMIT\s+(\d+)/i', $sql, $limitMatches)) {
                $limit = (int) $limitMatches[1];
                $results = \array_slice($results, 0, $limit);
            }

            /** @var list<array<string, bool|float|int|string|null>> $formattedResults */
            $formattedResults = array_values($results);

            return $formattedResults;
        }

        /** @var list<array<string, bool|float|int|string|null>> $emptyResult */
        $emptyResult = [];

        return $emptyResult;
    }

    /**
     * @param array<string, bool|float|int|string|null> $params
     */
    public function execute(string $sql, array $params = []): bool
    {
        if (str_starts_with(trim($sql), 'INSERT INTO')) {
            // Handle insert
            preg_match('/INSERT INTO\s+(\w+)/i', $sql, $matches);
            if (\count($matches) >= 2) {
                $tableName = $matches[1];

                if (!isset($this->tables[$tableName])) {
                    $this->tables[$tableName] = [];
                }

                $this->tables[$tableName][] = $params;
            }
        } elseif (str_starts_with(trim($sql), 'UPDATE')) {
            // Handle update
            preg_match('/UPDATE\s+(\w+)/i', $sql, $matches);
            if (\count($matches) >= 2) {
                $tableName = $matches[1];

                if (isset($this->tables[$tableName]) && preg_match('/WHERE\s+(\w+)\s+=\s+:(\w+)/i', $sql, $whereMatches)) {
                    $column = $whereMatches[1];
                    $paramName = $whereMatches[2];

                    foreach ($this->tables[$tableName] as $index => $row) {
                        if (isset($row[$column]) && $row[$column] === $params[$paramName]) {
                            // Remove the where condition param from the update data
                            $updateParams = $params;
                            unset($updateParams[$paramName]);

                            // Update the row
                            $this->tables[$tableName][$index] = array_merge($row, $updateParams);
                        }
                    }
                }
            }
        } elseif (str_starts_with(trim($sql), 'DELETE')) {
            // Handle delete
            preg_match('/DELETE FROM\s+(\w+)/i', $sql, $matches);
            if (\count($matches) >= 2) {
                $tableName = $matches[1];

                if (isset($this->tables[$tableName])) {
                    if (preg_match('/WHERE\s+(\w+)\s+=\s+:(\w+)/i', $sql, $whereMatches)) {
                        // Delete by specific column
                        $column = $whereMatches[1];
                        $paramName = $whereMatches[2];

                        $this->tables[$tableName] = array_filter(
                            $this->tables[$tableName],
                            static fn($row) => !isset($row[$column]) || $row[$column] !== $params[$paramName],
                        );
                        $this->tables[$tableName] = array_values($this->tables[$tableName]);
                    } elseif (preg_match('/WHERE\s+expires_at\s+<\s+:current_time/i', $sql)) {
                        // Handle deleteExpired
                        $this->tables[$tableName] = array_filter(
                            $this->tables[$tableName],
                            static fn($row) => !isset($row['expires_at']) || $row['expires_at'] >= $params['current_time'],
                        );
                        $this->tables[$tableName] = array_values($this->tables[$tableName]);
                    }
                }
            }
        }

        return true;
    }

    public function lastInsertId(): string
    {
        return '0';
    }

    public function transaction(callable $callback): mixed
    {
        $transaction = $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit($transaction);

            return $result;
        } catch (\Throwable $e) {
            $this->rollback($transaction);

            throw $e;
        }
    }

    /**
     * @param array<string, bool|float|int|string|null> $params
     */
    public function queryScalar(string $sql, array $params = []): null|string|int
    {
        $result = $this->query($sql, $params);
        if (empty($result) || empty($result[0])) {
            return null;
        }

        $value = reset($result[0]);
        if (\is_string($value) || \is_int($value) || $value === null) {
            return $value;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function beginTransaction(): array
    {
        // Store current state to support rollback
        return ['snapshot' => (string) serialize($this->tables)];
    }

    /**
     * @param array<string, mixed> $transaction
     */
    public function commit(array $transaction): void
    {
        // Nothing to do, changes are already in memory
    }

    /**
     * @param array<string, mixed> $transaction
     */
    public function rollback(array $transaction): void
    {
        // Restore from snapshot
        if (isset($transaction['snapshot']) && \is_string($transaction['snapshot'])) {
            /** @var array<string, array<int, array<string, mixed>>> $unserialized */
            $unserialized = unserialize($transaction['snapshot'], ['allowed_classes' => false]);
            $this->tables = $unserialized;
        }
    }
}
